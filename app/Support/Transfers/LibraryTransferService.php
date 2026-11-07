<?php

namespace App\Support\Transfers;

use App\Enums\LibraryImportDecision;
use App\Enums\LibraryImportItemStatus;
use App\Enums\LibraryTransferAction;
use App\Enums\LibraryTransferDirection;
use App\Enums\LibraryTransferOperation;
use App\Enums\LibraryTransferPartStatus;
use App\Enums\LibraryTransferRunStatus;
use App\Models\Genre;
use App\Models\LibraryTransferEntry;
use App\Models\LibraryTransferPart;
use App\Models\LibraryTransferRun;
use App\Models\Option;
use App\Models\Product;
use App\Support\LibraryMutationLock;
use App\Support\ProductImagePromotion;
use App\Support\UserStylesheetPromotion;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class LibraryTransferService
{
    private const UPLOAD_LOCK_WAIT_SECONDS = 10;

    public function __construct(
        private readonly TransferArchive $archive,
        private readonly WorkArchiveData $works,
        private readonly ImportReview $review,
        private readonly PortableOptions $options,
        private readonly TransferJobDispatcher $dispatcher,
        private readonly LibraryMutationLock $mutationLock,
        private readonly ProductImagePromotion $promotion,
        private readonly UserStylesheetPromotion $userStylesheetPromotion,
        private readonly TransferStorage $storage,
    ) {}

    public function uploadLimitBytes(): ?int
    {
        $limit = UploadedFile::getMaxFilesize();

        return $limit >= PHP_INT_MAX ? null : (int) $limit;
    }

    public function export(array $scopes, string $mode, array $ids): LibraryTransferRun
    {
        $this->dispatcher->assertReady();
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('transfer.export.zip_extension_required');
        }
        $ids = in_array('works', $scopes, true) && $mode === 'selected' ? $ids : [];
        $ids = array_map(fn($id) => is_string($id) ? strtoupper(trim($id)) : $id, $ids);
        Validator::make(compact('scopes', 'mode', 'ids'), [
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'distinct', Rule::in(['works', 'images', 'tag-library', 'options', 'user_css'])],
            'mode' => ['required', Rule::in(['all', 'selected'])],
            'ids' => ['array', Rule::exists('products', 'id')],
            'ids.*' => ['string', 'regex:/\A(?:RJ|BJ|VJ)\d+\z/'],
        ])->validate();
        if (in_array('images', $scopes, true) && ! in_array('works', $scopes, true)) {
            throw new RuntimeException('transfer.export.images_require_works');
        }
        $ids = ! in_array('works', $scopes, true)
            ? []
            : Product::query()->when($mode === 'selected', fn($query) => $query->whereKey(array_values(array_unique($ids))))
            ->orderByNumericCode()->pluck('id')->all();
        $ids = array_values(array_unique(array_map(fn($id) => strtoupper(trim($id)), $ids)));
        if (in_array('works', $scopes, true) && $ids === []) {
            throw new RuntimeException($mode === 'selected' ? 'transfer.export.select_work' : 'transfer.export.no_works');
        }
        $size = Option::exportPartMib();

        return $this->createRun(LibraryTransferDirection::Export, function () use ($scopes, $mode, $ids, $size): LibraryTransferRun {
            $run = LibraryTransferRun::create([
                'direction' => LibraryTransferDirection::Export,
                'status' => LibraryTransferRunStatus::Queued,
                'archive_set_id' => (string) Str::uuid(),
                'settings' => ['scopes' => $scopes, 'mode' => $mode, 'product_ids' => $ids, 'part_bytes' => $size === 'unlimited' ? null : $size * 1048576, 'exported_at' => now()->utc()->toIso8601ZuluString()]
            ]);
            $this->dispatcher->checkpoint($run, [], LibraryTransferOperation::Plan);

            return $run;
        });
    }

    public function startImport(): LibraryTransferRun
    {
        $this->dispatcher->assertReady();

        return $this->createRun(LibraryTransferDirection::Import, fn(): LibraryTransferRun => LibraryTransferRun::create([
            'direction' => LibraryTransferDirection::Import,
            'status' => LibraryTransferRunStatus::Uploading,
            'settings' => ['without_images' => false],
        ]));
    }

    /** Mark a published archive as recoverable when its filesystem state no longer matches the database. */
    public function exportPartAvailable(LibraryTransferRun $run, LibraryTransferPart $part): bool
    {
        $disk = Storage::disk('local');
        if (
            $part->path && $disk->exists($part->path)
            && ($part->sha256 === null || hash_file('sha256', $disk->path($part->path)) === $part->sha256)
        ) {
            return true;
        }

        DB::transaction(function () use ($run, $part): void {
            $lockedRun = LibraryTransferRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            $lockedPart = $lockedRun->parts()->whereKey($part->id)->lockForUpdate()->firstOrFail();
            if ($lockedRun->direction !== LibraryTransferDirection::Export || $lockedPart->status !== LibraryTransferPartStatus::Ready) {
                return;
            }
            $lockedPart->update(['status' => LibraryTransferPartStatus::Pending]);
            $lockedRun->update([
                'status' => LibraryTransferRunStatus::Failed,
                'error' => 'transfer.export.published_archive_invalid',
                'settings' => [
                    ...$lockedRun->settings,
                    'retry_operation' => LibraryTransferOperation::Build->value,
                    'retry_part' => $lockedPart->id,
                    'retry_status' => LibraryTransferRunStatus::Building->value,
                ],
            ]);
        });

        return false;
    }

    private function createRun(LibraryTransferDirection $direction, Closure $callback): LibraryTransferRun
    {
        try {
            return Cache::lock(LibraryTransferRun::LIFECYCLE_LOCK, config('transfers.lock_seconds'))
                ->block(0, fn(): LibraryTransferRun => DB::transaction(function () use ($direction, $callback): LibraryTransferRun {
                    $statuses = [
                        ...LibraryTransferRun::supersedableStatuses($direction),
                        ...($direction === LibraryTransferDirection::Import ? [LibraryTransferRunStatus::Applying] : []),
                    ];
                    $previous = LibraryTransferRun::query()
                        ->where('direction', $direction)
                        ->whereIn('status', $statuses)
                        ->lockForUpdate()
                        ->get(['id', 'status']);

                    if ($previous->contains('status', LibraryTransferRunStatus::Applying)) {
                        throw new RuntimeException('transfer.import.apply_in_progress');
                    }

                    $supersededIds = $previous
                        ->whereIn('status', LibraryTransferRun::supersedableStatuses($direction))
                        ->pluck('id');
                    if ($supersededIds->isNotEmpty()) {
                        LibraryTransferRun::query()
                            ->whereKey($supersededIds->all())
                            ->increment('generation', 1, ['status' => LibraryTransferRunStatus::Cancelled]);
                    }

                    return $callback();
                }));
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException('transfer.cleanup.busy', previous: $exception);
        }
    }

    public function upload(LibraryTransferRun $run, UploadedFile $file): LibraryTransferPart
    {
        $this->dispatcher->assertReady();

        return Cache::lock('transfer-upload-' . $run->id, config('transfers.lock_seconds'))->block(self::UPLOAD_LOCK_WAIT_SECONDS, function () use ($run, $file) {
            $run->refresh();
            if (! $run->acceptsReplacementParts()) {
                throw new RuntimeException('transfer.upload.not_accepting_parts');
            }
            $manifest = $this->archive->inspect($file->getRealPath());
            $common = Arr::only($manifest, ['format', 'schema_version', 'archive_set_id', 'exported_at', 'scopes', 'parts', 'work_selection', 'work_entry_format']);
            if (isset($run->settings['manifest']) && LibraryData::hash($run->settings['manifest']) !== LibraryData::hash($common)) {
                throw new InvalidArchive('transfer.upload.inconsistent_archive_set');
            }
            $existing = $run->parts()->where('kind', $manifest['part']['kind'])->where('number', $manifest['part']['number'])->first();
            if ($existing?->candidate) {
                throw new RuntimeException('transfer.upload.validation_pending');
            }
            if (! $existing && ! $run->acceptsNewParts()) {
                throw new RuntimeException('transfer.upload.not_accepting_new_parts');
            }
            $otherEntryCount = $run->parts()
                ->when($existing, fn($query) => $query->whereKeyNot($existing->id))
                ->sum('entry_count');
            if ($otherEntryCount + count($manifest['entries']) > config('transfers.max_entries')) {
                throw new InvalidArchive('transfer.archive.entry_count_safety_limit');
            }
            $bytes = $file->getSize();
            TransferArchive::space($bytes, Storage::disk('local')->path($run->directory()));
            $path = $this->storage->stageUpload($run, $file);

            try {
                return DB::transaction(function () use ($run, $manifest, $path, $bytes, $common, $existing) {
                    $run = LibraryTransferRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
                    if (! $run->acceptsReplacementParts()) {
                        throw new RuntimeException('transfer.upload.stopped_accepting_parts');
                    }
                    if ($existing) {
                        $part = $run->parts()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                        $part->update(['candidate' => [
                            'path' => $path,
                            'bytes' => $bytes,
                            'manifest' => $manifest,
                            'entry_count' => count($manifest['entries']),
                        ]]);
                        $this->dispatcher->checkpoint($run, [], LibraryTransferOperation::Inspect, $part->id);

                        return $part;
                    }
                    if (! $run->acceptsNewParts()) {
                        throw new RuntimeException('transfer.upload.stopped_accepting_new_parts');
                    }
                    $part = $run->parts()->create([
                        'kind' => $manifest['part']['kind'],
                        'number' => $manifest['part']['number'],
                        'upload_path' => $path,
                        'filename' => TransferArchive::filename($manifest['exported_at'], $manifest['part']['kind'], $manifest['part']['number'], $manifest['part']['count']),
                        'bytes' => $bytes,
                        'manifest' => $manifest,
                        'entry_count' => count($manifest['entries'])
                    ]);
                    $this->dispatcher->checkpoint($run, [
                        'status' => LibraryTransferRunStatus::WaitingForParts,
                        'archive_set_id' => $manifest['archive_set_id'],
                        'settings' => [...$run->settings, 'manifest' => $common, 'scopes' => $manifest['scopes'], 'parts' => $manifest['parts']]
                    ], LibraryTransferOperation::Inspect, $part->id);

                    return $part;
                });
            } catch (\Throwable $exception) {
                $this->storage->delete($path);
                throw $exception;
            }
        });
    }

    public function inspect(LibraryTransferRun $run, int $partId, ?string $uploadPath = null): void
    {
        if (in_array($run->status, [LibraryTransferRunStatus::Cancelled, LibraryTransferRunStatus::Failed, LibraryTransferRunStatus::Applying], true)) {
            return;
        }
        $part = $run->parts()->findOrFail($partId);
        if ($uploadPath !== null && $uploadPath !== $part->upload_path && $uploadPath !== ($part->candidate['path'] ?? null)) {
            return;
        }
        if ($part->kind === 'images' && ($run->settings['without_images'] ?? false)) {
            $part->update(['status' => LibraryTransferPartStatus::Excluded, 'candidate' => null]);

            return;
        }
        if ($part->status === LibraryTransferPartStatus::Pending) {
            $this->dispatcher->checkpoint($run, ['stage' => "Validating {$part->kind} part {$part->number}"]);
            $uploadPath = $part->upload_path;
            if (! is_string($uploadPath)) {
                throw new RuntimeException('transfer.upload.archive_unavailable');
            }
            $hash = hash_file('sha256', Storage::disk('local')->path($uploadPath));
            if ($hash === false) {
                throw new RuntimeException('transfer.upload.read_archive_failed');
            }
            $part->update(['sha256' => $hash]);
            try {
                $stage = $this->storage->stageDirectory($run, $uploadPath);
                $manifest = $this->archive->inspect(Storage::disk('local')->path($uploadPath), true, $stage);
                DB::transaction(function () use ($run, $part, $manifest, $stage): void {
                    $this->acceptEntries($run, $part, $manifest, $stage);
                    $part->update(['status' => LibraryTransferPartStatus::Valid, 'upload_path' => null, 'error' => null]);
                });
                $this->storage->delete($uploadPath);
            } catch (InvalidArchive $exception) {
                $part->entries()->delete();
                $part->update(['status' => LibraryTransferPartStatus::Invalid, 'upload_path' => null, 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
                $this->storage->deleteStage($run, $uploadPath);
                $this->storage->delete($uploadPath);
                if ($part->kind === 'data') {
                    throw $exception;
                }
            }
        }
        $part->refresh();
        if ($part->candidate && ($uploadPath === null || $uploadPath === $part->candidate['path'])) {
            $candidate = $part->candidate;
            $candidatePath = Storage::disk('local')->path($candidate['path']);
            $hash = hash_file('sha256', $candidatePath);
            if ($hash === false) {
                throw new RuntimeException('transfer.upload.read_duplicate_failed');
            }
            if ($part->status === LibraryTransferPartStatus::Invalid) {
                try {
                    $stage = $this->storage->stageDirectory($run, $candidate['path']);
                    $manifest = $this->archive->inspect($candidatePath, true, $stage);
                    DB::transaction(function () use ($run, $part, $manifest, $stage, $candidate, $hash): void {
                        $this->acceptEntries($run, $part, $manifest, $stage);
                        $part->update([
                            'upload_path' => null,
                            'bytes' => $candidate['bytes'],
                            'manifest' => $candidate['manifest'],
                            'entry_count' => $candidate['entry_count'],
                            'sha256' => $hash,
                            'status' => LibraryTransferPartStatus::Valid,
                            'error' => null,
                            'candidate' => null,
                        ]);
                    });
                    $this->storage->delete($candidate['path']);
                } catch (InvalidArchive $exception) {
                    $part->update(['candidate' => null, 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
                    $this->storage->deleteStage($run, $candidate['path']);
                    $this->storage->delete($candidate['path']);
                    if ($part->kind === 'data') {
                        throw $exception;
                    }
                }
            } else {
                $part->update(['candidate' => null, 'error' => $hash === $part->sha256 ? ($part->status === LibraryTransferPartStatus::Valid ? null : $part->error) : 'transfer.upload.different_content_rejected']);
                $this->storage->delete($candidate['path']);
            }
        }
        $this->storage->sweep($run);
        $this->maybeAnalyze($run->fresh());
    }

    private function acceptEntries(LibraryTransferRun $run, LibraryTransferPart $part, array $manifest, string $stage): void
    {
        foreach (array_chunk(array_column($manifest['entries'], 'path'), config('transfers.database_batch_records')) as $paths) {
            $duplicate = $run->entries()->where('library_transfer_part_id', '<>', $part->id)->whereIn('path', $paths)->value('path');
            if ($duplicate !== null) {
                throw new InvalidArchive('Duplicate entry across archive parts: ' . $duplicate);
            }
        }

        $part->entries()->delete();
        $base = ($part->number - 1) * config('transfers.max_entries');
        foreach (array_chunk($manifest['entries'], config('transfers.database_batch_records'), true) as $chunk) {
            $now = now();
            $rows = [];
            foreach ($chunk as $index => $entry) {
                preg_match('/\Aworks\/((?:RJ|BJ|VJ)\d+)\//', $entry['path'], $match);
                $rows[] = [
                    'library_transfer_run_id' => $run->id,
                    'library_transfer_part_id' => $part->id,
                    'generation' => 1,
                    'kind' => $part->kind,
                    'section' => $entry['section'],
                    'logical_key' => $entry[$manifest['schema_version'] === 1 ? 'rj_code' : 'product_code'] ?? ($match[1] ?? null),
                    'fragment_number' => $entry['fragment'] ?? null,
                    'fragment_count' => $entry['fragments'] ?? null,
                    'position' => $base + $index,
                    'path' => $entry['path'],
                    'source_disk' => 'local',
                    'source_path' => $stage . '/' . $entry['path'],
                    'bytes' => $entry['bytes'],
                    'sha256' => $entry['sha256'],
                    'media_type' => $entry['media_type'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            LibraryTransferEntry::insert($rows);
        }
    }

    public function dataReady(LibraryTransferRun $run): bool
    {
        return isset($run->settings['parts']) && $run->parts()->where('kind', 'data')->where('status', LibraryTransferPartStatus::Valid)->count() === $run->settings['parts']['data'];
    }

    public function maybeAnalyze(LibraryTransferRun $run): void
    {
        DB::transaction(function () use ($run): void {
            $run = LibraryTransferRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            if (! $run->acceptsNewParts()) {
                return;
            }
            if (! $this->dataReady($run)) {
                return;
            }
            $imagesReady = $run->parts()->where('kind', 'images')->where('status', LibraryTransferPartStatus::Valid)->count() === $run->settings['parts']['images'];
            if (! $imagesReady && ! $run->settings['without_images']) {
                return;
            }
            $this->dispatcher->checkpoint($run, ['status' => LibraryTransferRunStatus::Analyzing, 'stage' => 'Preparing review', 'processed' => 0], LibraryTransferOperation::Analyze);
        });
    }

    public function analyze(LibraryTransferRun $run): void
    {
        if ($run->status !== LibraryTransferRunStatus::Analyzing) {
            return;
        }
        if (! isset($run->settings['analysis_entries']) && ! $this->prepareAnalysisIndex($run)) {
            return;
        }
        $sections = array_diff($run->settings['scopes'], ['images']);
        $offset = $run->settings['analysis_fragment'] ?? 0;
        $recordOffset = $run->settings['analysis_record'] ?? 0;
        if (! $this->dispatcher->checkpoint($run, ['total' => $run->settings['analysis_entries'], 'processed' => $offset])) {
            return;
        }
        $batchCount = 0;
        $groupOrder = (int) ($run->settings['analysis_group_order'] ?? 0);
        foreach ($this->analysisEntries($run, $offset) as $fragmentIndex => $entry) {
            $path = Storage::disk($entry->source_disk)->path($entry->source_path);
            if (! is_file($path) || filesize($path) !== $entry->bytes || hash_file('sha256', $path) !== $entry->sha256) {
                throw new InvalidArchive('transfer.import.staged_data_changed');
            }
            $section = $entry->section;
            if ($section === 'user_css') {
                $css = file_get_contents($path);
                if ($css === false) {
                    throw new InvalidArchive('transfer.custom_css.read_staged_failed');
                }
                if (! mb_check_encoding($css, 'UTF-8')) {
                    throw new InvalidArchive('transfer.custom_css.invalid_utf8');
                }
                $records = [['css' => $css]];
            } else {
                $json = TransferArchive::decode(file_get_contents($path));
                if ($section === 'works') {
                    $key = $entry->logical_key;
                    $this->works->assertLayout($json, $key);
                    $records = [$json];
                } else {
                    if (($json['section'] ?? '') !== $section) {
                        throw new InvalidArchive('transfer.archive.invalid_fragment_envelope');
                    }
                    $validator = Validator::make($json, [
                        'records' => ['present', 'array', 'list', 'max:' . config('transfers.max_fragment_records')],
                    ]);
                    if ($validator->fails()) {
                        throw new InvalidArchive($validator->errors()->has('records.max')
                            ? 'transfer.archive.fragment_record_count_limit'
                            : 'transfer.archive.invalid_fragment_envelope');
                    }
                    $records = $validator->validated()['records'];
                }
            }
            $pending = array_slice($records, $fragmentIndex === $offset ? $recordOffset : 0, null, true);
            $prepared = [];
            foreach ($pending as $i => $record) {
                if (! is_array($record)) {
                    $record = ['invalid_record' => $record];
                }
                $key = match ($section) {
                    'works' => $entry->logical_key,
                    'user_css' => 'user.css',
                    default => $record['key'] ?? $entry->path . ':' . $i,
                };
                if (! is_string($key)) {
                    $key = $entry->path . ':' . $i;
                }
                $naturalKey = $section === 'works' ? strtoupper(trim($key)) : $key;
                if ($section === 'tag-library' && is_string($record['value']['title'] ?? null)) {
                    $naturalKey = Genre::titleKey($record['value']['title']);
                }
                $natural = LibraryData::hash([$section, $record['type'] ?? '', $naturalKey]);
                if (isset($prepared[$natural])) {
                    throw new InvalidArchive('Duplicate domain identity: ' . $key);
                }
                $prepared[$natural] = compact('record', 'key', 'i');
            }
            $existing = $run->items()->whereIn('record_identity', array_keys($prepared))->pluck('record_identity')->flip();
            foreach ($prepared as $natural => ['record' => $record, 'key' => $key, 'i' => $i]) {
                if (isset($existing[$natural])) {
                    throw new InvalidArchive('Duplicate domain identity: ' . $key);
                }
                $images = $section === 'works' ? $this->analysisImages($run, $key) : [];
                if ($section === 'tag-library' && ($record['type'] ?? '') === 'group') {
                    $record['_order'] = $groupOrder++;
                }
                DB::transaction(function () use ($run, $section, $record, $images, $natural, $key, $entry): void {
                    try {
                        if ($section === 'works') {
                            $record = $this->works->import($record, $key);
                        } elseif ($section === 'options' && ($run->settings['manifest']['schema_version'] ?? null) === 1 && is_string($record['key'] ?? null) && array_key_exists('value', $record)) {
                            $record['value'] = $this->options->normalizeLegacy($record['key'], $record['value']);
                        }
                        DB::transaction(fn() => $this->review->stage(
                            $run,
                            $section,
                            $record,
                            $images,
                            $natural,
                            $section === 'user_css' ? $entry->id : null,
                        ));
                    } catch (InvalidArgumentException | ValidationException $exception) {
                        $category = match ($section) {
                            'works' => 'new_works',
                            'options' => 'options',
                            'user_css' => 'user_css',
                            default => ($record['type'] ?? '') === 'group' ? 'groups' : 'tags'
                        };
                        $this->review->item($run, $section, $category, $key, null, $record, ['quarantine' => true, 'record_identity' => $natural], mb_substr($exception->getMessage(), 0, 2000));
                    }
                });
                $batchCount++;
                if ($batchCount >= config('transfers.analysis_batch_records')) {
                    $recordComplete = $i + 1 >= count($records);
                    $nextFragment = $recordComplete ? $fragmentIndex + 1 : $fragmentIndex;
                    $this->dispatcher->checkpoint($run, [
                        'settings' => [...$run->settings, 'analysis_fragment' => $nextFragment, 'analysis_record' => $recordComplete ? 0 : $i + 1, 'analysis_group_order' => $groupOrder],
                        'processed' => $nextFragment,
                        'stage' => "Preparing {$section} / {$key}",
                    ], LibraryTransferOperation::Analyze);

                    return;
                }
            }
            $offset = $fragmentIndex + 1;
            $recordOffset = 0;
        }
        if ($offset < $run->settings['analysis_entries']) {
            $this->dispatcher->checkpoint($run, ['processed' => $offset, 'settings' => [...$run->settings, 'analysis_fragment' => $offset, 'analysis_record' => 0, 'analysis_group_order' => $groupOrder]], LibraryTransferOperation::Analyze);

            return;
        }
        DB::transaction(function () use ($run, $sections): void {
            if (in_array('options', $sections, true)) {
                $presentKeys = $run->items()->where('section', 'options')->pluck('entity_key')->flip();
                foreach ($this->options->defaults() as $key => $default) {
                    if (! isset($presentKeys[$key])) {
                        $this->review->item($run, 'options', 'options', $key, $this->options->current($key), $default, ['missing' => true]);
                    }
                }
            }
            if (in_array('tag-library', $sections, true)) {
                $groupKeys = $run->items()->pending()->forSectionCategory('tag-library', 'groups')->orderBy('id')->pluck('entity_key')->all();
                $tagKeys = [];
                $edges = [];
                $graphBytes = 0;
                foreach ($run->items()->pending()->forSectionCategory('tag-library', 'tags')->lazyById(100) as $tag) {
                    $tagKeys[] = $tag->entity_key;
                    $graphBytes += strlen($tag->entity_key) + strlen(json_encode($tag->metadata['parents'] ?? [], JSON_THROW_ON_ERROR));
                    if ($graphBytes > config('transfers.max_json_bytes') || count($tagKeys) + count($edges) > config('transfers.max_entries')) {
                        throw new InvalidArchive('transfer.import.relationship_graph_limit');
                    }
                    foreach ($tag->metadata['parents'] ?? [] as $parent) {
                        $edges[] = [Genre::titleKey($parent), $tag->entity_key];
                    }
                }
                $tagKeyLookup = array_fill_keys($tagKeys, true);
                $badTags = $run->items()->forSectionCategory('tag-library', 'tags')->where('status', LibraryImportItemStatus::Unavailable)->exists();
                $badGroups = $run->items()->forSectionCategory('tag-library', 'groups')->where('status', LibraryImportItemStatus::Unavailable)->exists();
                $this->review->item($run, 'tag-library', 'groups', 'Group order and missing groups', $this->review->groupBaseline(), $groupKeys, ['collection' => true], $badGroups ? 'transfer.import.invalid_group_records' : null);
                $graphError = $badTags ? 'transfer.import.invalid_tags' : null;
                try {
                    foreach ($edges as [$parent, $child]) {
                        if (! isset($tagKeyLookup[$parent])) {
                            throw new InvalidArgumentException('transfer.import.relationship_missing_tag');
                        }
                    }
                } catch (InvalidArgumentException $exception) {
                    $graphError = $exception->getMessage();
                }
                $this->review->stageRelationships($run, $tagKeys, $edges, $graphError);
            }
            $this->dispatcher->checkpoint($run, ['status' => LibraryTransferRunStatus::Review, 'stage' => 'Waiting for approval']);
        });
    }

    /** Validate inventory ordering with bounded cursors; entry content is processed in later jobs. */
    private function prepareAnalysisIndex(LibraryTransferRun $run): bool
    {
        $dataEntries = $run->entries()->where('kind', 'data');
        foreach (array_diff($run->settings['scopes'], ['images']) as $section) {
            $fragments = (clone $dataEntries)->where('section', $section);
            if ($section === 'works') {
                if ($fragments->count() !== $run->settings['manifest']['work_selection']['count']) {
                    throw new InvalidArchive('transfer.archive.work_count_mismatch');
                }

                continue;
            }
            if ($section === 'user_css') {
                if ($fragments->count() !== 1) {
                    throw new InvalidArchive('transfer.custom_css.archive_layout_invalid');
                }

                continue;
            }
            if (! $fragments->exists()) {
                throw new InvalidArchive('Missing selected data section: ' . $section);
            }
            $expected = null;
            $number = 0;
            foreach ($fragments->orderBy('fragment_number')->cursor() as $fragment) {
                $expected ??= $fragment->fragment_count ?? 1;
                if (($fragment->fragment_count ?? 1) !== $expected || ($fragment->fragment_number ?? 1) !== ++$number) {
                    throw new InvalidArchive('transfer.archive.fragments_missing_or_inconsistent');
                }
            }
            if ($number !== $expected) {
                throw new InvalidArchive('transfer.archive.fragments_missing_or_inconsistent');
            }
        }

        return $this->dispatcher->checkpoint($run, ['settings' => [...$run->settings, 'analysis_entries' => $dataEntries->count()]]);
    }

    private function analysisEntries(LibraryTransferRun $run, int $offset): iterable
    {
        $entries = $run->entries()->where('kind', 'data')->orderBy('id')
            ->offset($offset)->limit(config('transfers.analysis_batch_records'))->get();
        foreach ($entries as $index => $entry) {
            yield $offset + $index => $entry;
        }
    }

    private function analysisImages(LibraryTransferRun $run, string $code): array
    {
        if (($run->settings['without_images'] ?? false) || ! preg_match('/\\A(?:RJ|BJ|VJ)\\d+\\z/', $code) || strlen($code) > 191) {
            return [];
        }

        return $run->entries()->where('kind', 'images')->where('logical_key', strtoupper($code))
            ->orderBy('position')->get()->mapWithKeys(fn(LibraryTransferEntry $entry) => [$entry->path => [
                'id' => $entry->id,
                'path' => $entry->path,
                'sha256' => $entry->sha256,
                'bytes' => $entry->bytes,
                'media_type' => $entry->media_type,
            ]])->all();
    }

    public function action(LibraryTransferRun $run, LibraryTransferAction $action, ?string $section = null, ?string $category = null): void
    {
        if ($action === LibraryTransferAction::Cancel) {
            $this->cancel($run);

            return;
        }
        Cache::lock('transfer-run-' . $run->id, config('transfers.lock_seconds'))->block(0, function () use ($run, $action, $section, $category): void {
            $run->refresh();
            if ($action === LibraryTransferAction::Cleanup) {
                $this->cleanup($run);

                return;
            }
            DB::transaction(function () use ($run, $action, $section, $category): void {
                $run = LibraryTransferRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
                match ($action) {
                    LibraryTransferAction::ContinueExport => $this->continueExport($run),
                    LibraryTransferAction::WithoutImages => $this->continueWithoutImages($run),
                    LibraryTransferAction::Retry => $this->retry($run),
                    LibraryTransferAction::Apply => $this->beginApply($run, $section, $category),
                    LibraryTransferAction::Refresh => $this->refreshConflicts($run),
                    default => $this->unavailableAction(),
                };
            });
        });
    }

    private function cancel(LibraryTransferRun $run): void
    {
        if (! LibraryTransferRun::whereKey($run->id)
            ->whereIn('status', LibraryTransferRun::supersedableStatuses($run->direction))
            ->increment('generation', 1, ['status' => LibraryTransferRunStatus::Cancelled])) {
            throw new RuntimeException('transfer.cancel.unavailable');
        }
    }

    private function cleanup(LibraryTransferRun $run): void
    {
        if ($run->busy()) {
            $this->unavailableAction();
        }

        Cache::lock('transfer-upload-' . $run->id, config('transfers.lock_seconds'))->block(0, function () use ($run): void {
            $this->mutationLock->run(function () use ($run): void {
                $this->promotion->recover();
                $this->userStylesheetPromotion->recover();
                DB::transaction(function () use ($run): void {
                    $this->storage->deleteRun($run);
                    $run->delete();
                });
            });
        });
    }

    private function continueExport(LibraryTransferRun $run): void
    {
        if ($run->status !== LibraryTransferRunStatus::AwaitingConfirmation) {
            $this->unavailableAction();
        }

        $this->dispatcher->assertReady();
        $this->dispatcher->checkpoint($run, ['status' => LibraryTransferRunStatus::Building]);
        $this->nextBuild($run);
    }

    private function continueWithoutImages(LibraryTransferRun $run): void
    {
        if ($run->status !== LibraryTransferRunStatus::WaitingForParts || ! $this->dataReady($run)) {
            $this->unavailableAction();
        }

        $this->dispatcher->checkpoint($run, ['settings' => [...$run->settings, 'without_images' => true, 'retry_operation' => null]]);
        $this->maybeAnalyze($run);
    }

    private function retry(LibraryTransferRun $run): void
    {
        $operation = $run->retryOperation();
        $status = $run->retryStatus();
        if (
            ! in_array($run->status, [LibraryTransferRunStatus::Failed, LibraryTransferRunStatus::WaitingForParts], true)
            || $operation === null || $status === null
        ) {
            $this->unavailableAction();
        }

        $this->dispatcher->assertReady();
        $inspect = $operation === LibraryTransferOperation::Inspect;
        $settings = [...$run->settings, 'retry_operation' => null];
        if ($operation === LibraryTransferOperation::Analyze) {
            unset($settings['analysis_entries']);
        }
        if ($inspect) {
            $run->parts()->whereKey($run->settings['retry_part'])->where('status', '<>', LibraryTransferPartStatus::Valid)
                ->update(['status' => LibraryTransferPartStatus::Pending]);
        }
        $this->dispatcher->checkpoint($run, [
            'status' => $status,
            'generation' => $run->generation + 1,
            'error' => null,
            'settings' => $settings,
        ], $inspect ? null : $operation, $run->settings['retry_part'] ?? null);
        if ($inspect) {
            $this->queuePendingInspections($run);
        }
    }

    private function beginApply(LibraryTransferRun $run, ?string $section, ?string $category): void
    {
        if (! $run->reviewable()) {
            $this->unavailableAction();
        }

        $this->dispatcher->assertReady();
        if ($section !== null && (! isset(ImportReview::CATEGORIES[$section]) || ($category !== null && ! in_array($category, ImportReview::CATEGORIES[$section], true)))) {
            throw new RuntimeException('transfer.review.invalid_category');
        }
        $total = $run->items()->pending()->forSectionCategory($section, $category)->count();
        if ($total !== 0) {
            $this->dispatcher->checkpoint($run, [
                'status' => LibraryTransferRunStatus::Applying,
                'generation' => $run->generation + 1,
                'stage' => 'Applying reviewed changes',
                'error' => null,
                'processed' => 0,
                'total' => $total,
                'settings' => [...$run->settings, 'apply_section' => $section, 'apply_category' => $category],
            ], LibraryTransferOperation::Apply);
        }
    }

    private function refreshConflicts(LibraryTransferRun $run): void
    {
        if (! $run->reviewable()) {
            $this->unavailableAction();
        }

        $this->review->refreshRelationships($run);
        foreach (
            $run->items()->whereIn('status', [LibraryImportItemStatus::Conflict, LibraryImportItemStatus::Failed])
                ->where('category', '<>', 'relationships')->lazyById(100) as $item
        ) {
            $item->baseline = $this->review->current($item);
            $item->baseline_preview = ImportValue::preview($item, 'baseline');
            $item->decision = LibraryImportDecision::Ignore;
            $item->decision_override = true;
            $item->status = LibraryImportItemStatus::Pending;
            $item->error = null;
            $item->save();
        }
        $this->dispatcher->checkpoint($run, ['status' => LibraryTransferRunStatus::Review]);
    }

    private function unavailableAction(): never
    {
        throw new RuntimeException('transfer.state.action_unavailable');
    }

    public function nextBuild(LibraryTransferRun $run): void
    {
        if ($run->status !== LibraryTransferRunStatus::Building) {
            return;
        }
        $part = $run->parts()->where('status', LibraryTransferPartStatus::Pending)->orderBy('id')->first();
        if ($part) {
            $this->dispatcher->checkpoint($run, [], LibraryTransferOperation::Build, $part->id);
        }
    }

    private function queuePendingInspections(LibraryTransferRun $run): void
    {
        foreach ($run->parts()->awaitingInspection()->lazyById(100) as $part) {
            $this->dispatcher->checkpoint($run, [], LibraryTransferOperation::Inspect, $part->id);
        }
    }

    public function apply(LibraryTransferRun $run): void
    {
        if ($run->status !== LibraryTransferRunStatus::Applying) {
            return;
        }
        $this->mutationLock->run(function () use ($run): void {
            $this->promotion->recover();
            $this->userStylesheetPromotion->recover();
            $query = $run->items()->pending()->forSectionCategory(
                $run->settings['apply_section'] ?? null,
                $run->settings['apply_category'] ?? null,
            );
            $batchCount = 0;
            $workQuery = (clone $query)->where('section', 'works');
            $workLimit = max(1, intdiv(config('transfers.apply_batch_items'), count(ImportReview::CATEGORIES['works'])));
            $workIds = (clone $workQuery)->select('entity_key')->distinct()->orderBy('entity_key')->limit($workLimit)->pluck('entity_key');
            foreach ($workIds as $workId) {
                $items = (clone $workQuery)->where('entity_key', $workId)->orderBy('id')->get();
                $batchCount += $items->count();
                $this->review->applyWorkItems($items);
            }
            if ((clone $workQuery)->exists()) {
                $remaining = (clone $query)->count();
                $this->dispatcher->checkpoint($run, [
                    'processed' => $run->total - $remaining,
                    'stage' => "Applying works / work / {$workIds->last()}",
                ], LibraryTransferOperation::Apply);

                return;
            }
            foreach (ImportReview::CATEGORIES as $section => $categories) {
                if ($section === 'works') {
                    continue;
                }
                foreach ($categories as $category) {
                    // The collection order decision precedes individual group creation/metadata.
                    $items = (clone $query)->where('section', $section)->where('category', $category);
                    if ($category === 'groups' && $section === 'tag-library') {
                        $items->orderByDesc('collection');
                    }
                    $items->orderBy('id');
                    if ($section === 'tag-library' && $category === 'relationships') {
                        if ($items->exists()) {
                            $this->review->applyRelationshipChoices($run);
                        }

                        continue;
                    }
                    foreach ($items->limit(config('transfers.apply_batch_items') - $batchCount)->get() as $item) {
                        $batchCount++;
                        $this->review->applyItem($item);
                        if ($batchCount >= config('transfers.apply_batch_items')) {
                            $remaining = (clone $query)->count();
                            $this->dispatcher->checkpoint($run, [
                                'processed' => $run->total - $remaining,
                                'stage' => "Applying {$section} / {$category} / {$item->entity_key}",
                            ], $remaining > 0 ? LibraryTransferOperation::Apply : null);
                            if ($remaining > 0) {
                                return;
                            }

                            break 3;
                        }
                    }
                }
            }
            $status = $run->items()->whereIn('status', [LibraryImportItemStatus::Pending, LibraryImportItemStatus::Conflict, LibraryImportItemStatus::Failed])->exists()
                ? LibraryTransferRunStatus::Review
                : ($run->items()->where(fn($q) => $q->where('status', LibraryImportItemStatus::Unavailable)->orWhereNotNull('metadata->warning'))->exists()
                    ? LibraryTransferRunStatus::CompletedWithWarnings
                    : LibraryTransferRunStatus::Completed);
            DB::transaction(function () use ($run, $status, $query): void {
                if ($this->dispatcher->checkpoint($run, ['status' => $status, 'stage' => 'Review results', 'processed' => $run->total - (clone $query)->count()])) {
                    // Apply advances the generation: resume any duplicate uploads queued before it.
                    $this->queuePendingInspections($run);
                }
            });
        });
    }
}
