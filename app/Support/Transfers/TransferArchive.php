<?php

namespace App\Support\Transfers;

use App\Enums\LibraryTransferOperation;
use App\Enums\LibraryTransferPartStatus;
use App\Enums\LibraryTransferRunStatus;
use App\Models\Genre;
use App\Models\GenreGroup;
use App\Models\LibraryTransferEntry;
use App\Models\LibraryTransferPart;
use App\Models\LibraryTransferRun;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;
use ZipArchive;

final class TransferArchive
{
    public function __construct(
        private readonly LibraryData $data,
        private readonly WorkArchiveData $works,
        private readonly PortableOptions $options,
        private readonly TransferJobDispatcher $dispatcher,
        private readonly TransferStorage $storage,
    ) {}

    public static function filename(string $date, string $kind, int $number, int $count): string
    {
        $width = max(2, strlen((string) $count));

        return 'dlsite-list_' . gmdate('Ymd_His\Z', strtotime($date)) . '.' . $kind . '.part' . str_pad((string) $number, $width, '0', STR_PAD_LEFT) . '-of-' . str_pad((string) $count, $width, '0', STR_PAD_LEFT) . '.zip';
    }

    public function plan(LibraryTransferRun $run): void
    {
        DB::transaction(function () use ($run): void {
            $current = LibraryTransferRun::whereKey($run->id)->lockForUpdate()->first();
            if (
                ! $current || $current->generation !== $run->generation || $current->status !== $run->status
                || ! in_array($current->status, [LibraryTransferRunStatus::Queued, LibraryTransferRunStatus::Planning], true)
            ) {
                return;
            }

            // Inventory, cursors and the successor job must commit as one batch.
            $this->planBatch($current);
        });
    }

    private function planBatch(LibraryTransferRun $run): void
    {
        if (! $this->initializePlanning($run)) {
            return;
        }
        $run = $run->fresh();
        match ($run->settings['planning']['phase']) {
            'works' => $this->planWorks($run),
            'tag-library' => $this->planTagLibrary($run),
            'options' => $this->planOptions($run),
            'pack-data' => $this->packEntries($run, 'data'),
            'pack-images' => $this->packEntries($run, 'images'),
            'normalize-fragments' => $this->normalizeFragments($run),
            'finalize' => $this->finalizeParts($run),
            default => throw new RuntimeException('Invalid export planning checkpoint.'),
        };
    }

    private function initializePlanning(LibraryTransferRun $run): bool
    {
        if ($run->status === LibraryTransferRunStatus::Planning && is_array($run->settings['planning'] ?? null)) {
            return true;
        }
        if ($run->status !== LibraryTransferRunStatus::Queued) {
            return false;
        }

        $scopes = $run->settings['scopes'];
        $phase = in_array('works', $scopes, true)
            ? 'works'
            : (in_array('tag-library', $scopes, true) ? 'tag-library' : 'options');
        $planning = [
            'phase' => $phase,
            'work_offset' => 0,
            'tag_type' => 'tags',
            'tag_offset' => 0,
            'fragment_count' => 0,
            'position' => 0,
            'pack' => [],
            'fragment_cursor' => 0,
            'part_cursor' => 0,
        ];

        return DB::transaction(function () use ($run, $planning, $phase): bool {
            $run->entries()->delete();
            $run->parts()->delete();

            return $this->dispatcher->checkpoint($run, [
                'status' => LibraryTransferRunStatus::Planning,
                'stage' => $this->phaseLabel($phase, $run->settings['scopes']),
                'processed' => 0,
                'total' => count($run->settings['product_ids'] ?? []),
                'warnings' => [],
                'settings' => [...$run->settings, 'planning' => $planning],
            ]);
        });
    }

    private function planWorks(LibraryTransferRun $run): void
    {
        $settings = $run->settings;
        $planning = $settings['planning'];
        $ids = $settings['product_ids'] ?? [];
        $offset = (int) $planning['work_offset'];
        $batch = array_slice($ids, $offset, config('transfers.database_batch_records'));
        $files = [];
        $warnings = $run->warnings ?? [];
        $withImages = in_array('images', $settings['scopes'], true);

        foreach ($batch as $id) {
            $product = Product::find($id);
            if (! $product) {
                $warnings[] = $id . ': work was removed before export planning.';
                $offset++;

                continue;
            }
            $snapshot = $this->works->exportSnapshot($product, $withImages);
            foreach ($snapshot['image_warnings'] as $image) {
                $warnings[] = $id . ': ' . $image . ' is incomplete.';
            }
            $work = $snapshot['work'];
            foreach (['cover', 'sample_images'] as $category) {
                foreach ($work['dlsite_list'][$category]['files'] ?? [] as $index => $file) {
                    $files[] = [...$file, 'disk' => 'public', 'section' => $category, 'rj_code' => $id];
                    unset($work['dlsite_list'][$category]['files'][$index]['source']);
                }
            }
            $files[] = $this->workEntry($run, $id, $work);
            $offset++;
        }
        $this->insertEntries($run, $files, $planning);

        $planning['work_offset'] = $offset;
        if ($offset >= count($ids)) {
            $planning['phase'] = $this->nextInventoryPhase($settings['scopes'], 'works');
        }
        $last = $batch === [] ? null : end($batch);
        $stage = $last === null
            ? $this->phaseLabel($planning['phase'], $settings['scopes'])
            : ($withImages
                ? __('Preparing :code and its images for export', ['code' => $last])
                : __('Preparing :code for export', ['code' => $last]));
        $this->continuePlanning($run, $planning, $stage, $offset, $warnings);
    }

    private function planTagLibrary(LibraryTransferRun $run): void
    {
        $planning = $run->settings['planning'];
        $type = $planning['tag_type'];
        $offset = (int) $planning['tag_offset'];
        $limit = (int) config('transfers.database_batch_records');
        $models = $type === 'tags'
            ? Genre::query()->with('parents')->orderBy('title_key')->orderBy('id')->offset($offset)->limit($limit)->get()
            : GenreGroup::query()->ordered()->orderBy('id')->with('genres')->offset($offset)->limit($limit)->get();
        $records = $models->map(fn($model): array => $type === 'tags'
            ? ['type' => 'tag', 'key' => Genre::titleKey($model->title), 'value' => [...Arr::only($model->attributesToArray(), LibraryData::METADATA), 'parents' => $model->parents->pluck('title')->all()]]
            : ['type' => 'group', 'key' => Genre::titleKey($model->title), 'value' => [...Arr::only($model->attributesToArray(), LibraryData::METADATA), 'members' => $model->genres->pluck('title')->all()]])->all();

        if ($records !== []) {
            $fragments = $this->fragments($run, 'tag-library', $records, (int) $planning['fragment_count']);
            $planning['fragment_count'] += count($fragments);
            $this->insertEntries($run, $fragments, $planning);
            $planning['tag_offset'] += count($records);
        }
        if (count($records) < $limit) {
            if ($type === 'tags') {
                $planning['tag_type'] = 'groups';
                $planning['tag_offset'] = 0;
            } else {
                if ((int) $planning['fragment_count'] === 0) {
                    $fragments = $this->fragments($run, 'tag-library', [], 0);
                    $planning['fragment_count'] = count($fragments);
                    $this->insertEntries($run, $fragments, $planning);
                }
                $planning['phase'] = $this->nextInventoryPhase($run->settings['scopes'], 'tag-library');
            }
        }
        $this->continuePlanning($run, $planning, 'Tag Library', $run->processed);
    }

    private function planOptions(LibraryTransferRun $run): void
    {
        $planning = $run->settings['planning'];
        $records = collect($this->options->all())->map(fn($value, $key): array => compact('key', 'value'))->values()->all();
        $fragments = $this->fragments($run, 'options', $records);
        $planning['fragment_count'] = count($fragments);
        $this->insertEntries($run, $fragments, $planning);
        $planning['phase'] = 'pack-data';
        $this->continuePlanning($run, $planning, 'Options', $run->processed);
    }

    private function insertEntries(LibraryTransferRun $run, array $files, array &$planning): void
    {
        if ($files === []) {
            return;
        }
        if ($run->entries()->count() + count($files) > config('transfers.max_entries')) {
            throw new RuntimeException('Too many archive entries. Select fewer works or increase the server safety limit.');
        }
        $now = now();
        $rows = [];
        foreach ($files as $file) {
            $rows[] = [
                'library_transfer_run_id' => $run->id,
                'library_transfer_part_id' => null,
                'generation' => $run->generation,
                'kind' => ($file['section'] === 'cover' || $file['section'] === 'sample_images') ? 'images' : 'data',
                'section' => $file['section'],
                'logical_key' => $file['rj_code'] ?? null,
                'fragment_number' => $file['fragment'] ?? null,
                'fragment_count' => $file['fragments'] ?? null,
                'position' => $planning['position']++,
                'path' => $file['path'],
                'source_disk' => $file['disk'],
                'source_path' => $file['source'],
                'bytes' => $file['bytes'],
                'sha256' => $file['sha256'],
                'media_type' => $file['media_type'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, config('transfers.database_batch_records')) as $chunk) {
            LibraryTransferEntry::insert($chunk);
        }
    }

    private function continuePlanning(LibraryTransferRun $run, array $planning, string $stage, int $processed, ?array $warnings = null): void
    {
        $attributes = [
            'settings' => [...$run->settings, 'planning' => $planning],
            'stage' => $stage,
            'processed' => $processed,
        ];
        if ($warnings !== null) {
            $attributes['warnings'] = $warnings;
        }
        $this->dispatcher->checkpoint($run, $attributes, LibraryTransferOperation::Plan);
    }

    private function nextInventoryPhase(array $scopes, string $completed): string
    {
        $order = ['works', 'tag-library', 'options'];
        $position = array_search($completed, $order, true);
        foreach (array_slice($order, $position === false ? 0 : $position + 1) as $scope) {
            if (in_array($scope, $scopes, true)) {
                return $scope;
            }
        }

        return 'pack-data';
    }

    private function phaseLabel(string $phase, array $scopes): string
    {
        return match ($phase) {
            'works' => in_array('images', $scopes, true) ? 'Works and Images' : 'Works',
            'tag-library' => 'Tag Library',
            'options' => 'Options',
            default => 'Archive parts',
        };
    }

    private function packEntries(LibraryTransferRun $run, string $kind): void
    {
        if ($kind === 'data' && ! $run->entries()->where('kind', 'data')->exists()) {
            throw new InvalidArchive('No exportable data remains. Start a new export after adding a work.');
        }
        $planning = $run->settings['planning'];
        $state = $planning['pack'][$kind] ?? [
            'cursor' => 0,
            'part_id' => null,
            'part_number' => 0,
            'bytes' => 4096,
            'manifest_bytes' => 4096,
            'entries' => 0,
        ];
        $entries = $run->entries()->where('kind', $kind)->where('id', '>', $state['cursor'])
            ->orderBy('id')->limit(config('transfers.database_batch_records'))->get();

        foreach ($entries as $entry) {
            $inventoryBytes = strlen(json_encode($this->manifestEntry($entry), JSON_THROW_ON_ERROR)) + 1;
            $entryBytes = $this->entryBound($entry->path, $entry->bytes);
            $limit = $run->settings['part_bytes'];
            if (($limit !== null && $entryBytes + 4096 > $limit) || $inventoryBytes + 4096 > config('transfers.max_manifest_bytes')) {
                throw new InvalidArchive('Entry cannot fit in one part: ' . $entry->path . '. Start a new export with a larger or Unlimited size.');
            }
            $full = $state['entries'] > 0 && (
                ($limit !== null && $limit < $state['bytes'] + $entryBytes)
                || $state['entries'] >= config('transfers.max_entries') - 1
                || config('transfers.max_manifest_bytes') < $state['manifest_bytes'] + $inventoryBytes
            );
            if ($state['part_id'] === null || $full) {
                $number = $state['part_number'] + 1;
                $part = $run->parts()->create([
                    'kind' => $kind,
                    'number' => $number,
                    'filename' => 'planning-' . $kind . '-' . $number . '.zip',
                    'path' => null,
                    'manifest' => [],
                ]);
                $state = [
                    ...$state,
                    'part_id' => $part->id,
                    'part_number' => $number,
                    'bytes' => 4096,
                    'manifest_bytes' => 4096,
                    'entries' => 0,
                ];
                if ($run->parts()->count() > config('transfers.max_parts')) {
                    throw new RuntimeException('Too many archive parts. Choose a larger part size.');
                }
            }
            $entry->update(['library_transfer_part_id' => $state['part_id']]);
            LibraryTransferPart::whereKey($state['part_id'])->update([
                'bytes' => $state['bytes'] + $entryBytes,
                'entry_count' => $state['entries'] + 1,
            ]);
            $state['bytes'] += $entryBytes;
            $state['manifest_bytes'] += $inventoryBytes;
            $state['entries']++;
            $state['cursor'] = $entry->id;
        }

        $planning['pack'][$kind] = $state;
        if ($entries->count() < config('transfers.database_batch_records')) {
            $planning['phase'] = $kind === 'data' ? 'pack-images' : 'normalize-fragments';
        }
        $this->continuePlanning($run, $planning, 'Archive parts', $run->processed);
    }

    private function normalizeFragments(LibraryTransferRun $run): void
    {
        $planning = $run->settings['planning'];
        $entries = $run->entries()->whereNotNull('fragment_number')->where('id', '>', $planning['fragment_cursor'])
            ->orderBy('id')->limit(config('transfers.database_batch_records'))->get();
        $counts = $run->entries()->whereNotNull('fragment_number')->selectRaw('section, COUNT(*) AS aggregate')->groupBy('section')->pluck('aggregate', 'section');
        foreach ($entries as $entry) {
            $count = (int) $counts[$entry->section];
            $entry->update([
                'fragment_count' => $count,
                'path' => $entry->section . ($count === 1 ? '' : '.part' . str_pad((string) $entry->fragment_number, 4, '0', STR_PAD_LEFT)) . '.json',
            ]);
            $planning['fragment_cursor'] = $entry->id;
        }
        if ($entries->count() < config('transfers.database_batch_records')) {
            $planning['phase'] = 'finalize';
        }
        $this->continuePlanning($run, $planning, 'Archive parts', $run->processed);
    }

    private function finalizeParts(LibraryTransferRun $run): void
    {
        $planning = $run->settings['planning'];
        $counts = [
            'data' => $run->parts()->where('kind', 'data')->count(),
            'images' => $run->parts()->where('kind', 'images')->count(),
        ];
        if (array_sum($counts) > config('transfers.max_parts')) {
            throw new RuntimeException('Too many archive parts. Choose a larger part size.');
        }
        $parts = $run->parts()->where('id', '>', $planning['part_cursor'])->orderBy('id')->limit(25)->get();
        $workCount = $run->entries()->where('section', 'works')->count();
        foreach ($parts as $part) {
            $name = self::filename($run->settings['exported_at'], $part->kind, $part->number, $counts[$part->kind]);
            $manifest = [
                'format' => 'dlsite-list',
                'schema_version' => 1,
                'archive_set_id' => $run->archive_set_id,
                'exported_at' => $run->settings['exported_at'],
                'scopes' => $run->settings['scopes'],
                'work_selection' => ['mode' => $run->settings['mode'], 'count' => $workCount],
                'part' => ['kind' => $part->kind, 'number' => $part->number, 'count' => $counts[$part->kind]],
                'parts' => $counts,
                'entries' => $part->entries()->orderBy('position')->get()->map($this->manifestEntry(...))->all(),
            ];
            if (in_array('works', $run->settings['scopes'], true)) {
                $manifest['work_entry_format'] = WorkArchiveData::FORMAT;
            }
            if (strlen(json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > config('transfers.max_manifest_bytes')) {
                throw new InvalidArchive('Manifest is too large; choose smaller archive parts.');
            }
            $part->update([
                'filename' => $name,
                'path' => $run->directory() . '/archives/' . $name,
                'manifest' => $manifest,
            ]);
            $planning['part_cursor'] = $part->id;
        }
        if ($parts->count() === 25) {
            $this->continuePlanning($run, $planning, 'Archive parts', $run->processed);

            return;
        }

        $settings = [...$run->settings, 'planning' => null, 'parts' => $counts];
        $status = array_sum($counts) > 10 ? LibraryTransferRunStatus::AwaitingConfirmation : LibraryTransferRunStatus::Building;
        if ($this->dispatcher->checkpoint($run, [
            'settings' => $settings,
            'stage' => 'Archive parts',
            'processed' => 0,
            'total' => array_sum($counts),
            'status' => $status,
        ])) {
            $this->nextBuild($run);
        }
    }

    private function manifestEntry(LibraryTransferEntry $entry): array
    {
        return Arr::whereNotNull([
            'path' => $entry->path,
            'section' => $entry->section,
            'rj_code' => $entry->logical_key,
            'fragment' => $entry->fragment_number,
            'fragments' => $entry->fragment_count,
            'bytes' => $entry->bytes,
            'sha256' => $entry->sha256,
            'media_type' => $entry->media_type,
        ]);
    }

    private function entryBound(string $path, int $bytes): int
    {
        return $bytes + 2048 + strlen($path) * 4;
    }

    private function workEntry(LibraryTransferRun $run, string $id, array $work): array
    {
        $json = json_encode($work, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > config('transfers.max_json_bytes')) {
            throw new InvalidArchive('Domain record exceeds the configured JSON safety limit: ' . $id);
        }
        $source = $run->directory() . '/data/works/' . $id . '.json';
        self::space(strlen($json), Storage::disk('local')->path($run->directory()));
        Storage::disk('local')->makeDirectory(dirname($source));
        if (! Storage::disk('local')->put($source, $json)) {
            throw new RuntimeException('Unable to stage export work data.');
        }

        return [
            'path' => 'works/' . $id . '/work.json',
            'source' => $source,
            'disk' => 'local',
            'bytes' => strlen($json),
            'sha256' => hash('sha256', $json),
            'media_type' => 'application/json',
            'section' => 'works',
            'rj_code' => $id,
        ];
    }

    private function fragments(LibraryTransferRun $run, string $section, iterable $records, int $start = 0): array
    {
        $maximum = min(config('transfers.fragment_bytes'), ($run->settings['part_bytes'] ?? PHP_INT_MAX) - 8192);
        if ($maximum < 128) {
            throw new RuntimeException('Part size is too small for an archive manifest.');
        }
        $fragments = [];
        $buffer = [];
        $bytes = 64;
        $write = function () use (&$fragments, &$buffer, &$bytes, $run, $section, $start): void {
            $number = $start + count($fragments) + 1;
            $path = $run->directory() . '/data/' . $section . '.' . $number . '.json';
            $json = json_encode(['section' => $section, 'records' => $buffer], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($json) > config('transfers.max_json_bytes')) {
                throw new RuntimeException('A ' . $section . ' record exceeds the supported JSON record size.');
            }
            self::space(strlen($json), Storage::disk('local')->path($run->directory()));
            if (! Storage::disk('local')->put($path, $json)) {
                throw new RuntimeException('Unable to stage export data.');
            }
            $fragments[] = ['source' => $path, 'disk' => 'local', 'bytes' => strlen($json), 'sha256' => hash('sha256', $json), 'media_type' => 'application/json', 'section' => $section, 'fragment' => $number, 'fragments' => 0, 'path' => $section . '.part' . str_pad((string) $number, 4, '0', STR_PAD_LEFT) . '.json'];
            $buffer = [];
            $bytes = 64;
        };
        foreach ($records as $record) {
            if (LibraryTransferRun::whereKey($run->id)->value('status') === LibraryTransferRunStatus::Cancelled->value) {
                throw new RuntimeException('Export cancelled.');
            }
            $length = strlen(json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) + 1;
            if ($length + 64 > config('transfers.max_json_bytes') || ($run->settings['part_bytes'] !== null && $this->bound([['path' => $section . '.part0001.json', 'bytes' => $length + 64]]) > $run->settings['part_bytes'])) {
                throw new InvalidArchive('Domain record cannot fit in one supported fragment: ' . ($record['rj_code'] ?? $record['key'] ?? $section));
            }
            if ($buffer !== [] && ($bytes + $length > $maximum || count($buffer) >= config('transfers.max_fragment_records'))) {
                $write();
            }
            $buffer[] = $record;
            $bytes += $length;
            if (count($fragments) >= config('transfers.max_entries')) {
                throw new RuntimeException('Too many archive fragments. Select less data or increase the server safety limit.');
            }
        }
        if ($buffer !== [] || $fragments === []) {
            $write();
        }

        return $fragments;
    }

    public function bound(array $files): int
    {
        return 4096 + array_sum(array_map(fn($file) => $this->entryBound($file['path'], $file['bytes']), $files));
    }

    public function build(LibraryTransferPart $part): void
    {
        $run = $part->run;
        if ($run->status !== LibraryTransferRunStatus::Building) {
            return;
        }
        if ($part->status === LibraryTransferPartStatus::Ready) {
            if (! $run->parts()->where('status', '<>', LibraryTransferPartStatus::Ready)->exists()) {
                $this->dispatcher->checkpoint($run, [
                    'status' => LibraryTransferRunStatus::Ready,
                    'processed' => $run->parts()->where('status', LibraryTransferPartStatus::Ready)->count(),
                    'stage' => __('Archive parts'),
                ]);
            }

            return;
        }
        $manifest = $part->manifest;
        $disk = Storage::disk('local');
        if ($part->path && $disk->exists($part->path)) {
            try {
                $publishedManifest = $this->inspect($disk->path($part->path), true);
                if (
                    LibraryData::hash($publishedManifest) === LibraryData::hash($manifest)
                    && ($run->settings['part_bytes'] === null || $disk->size($part->path) <= $run->settings['part_bytes'])
                ) {
                    $part->update([
                        'status' => LibraryTransferPartStatus::Ready,
                        'bytes' => $disk->size($part->path),
                        'sha256' => hash_file('sha256', $disk->path($part->path)),
                    ]);
                    $this->buildProgress($run, $part);

                    return;
                }
            } catch (InvalidArchive) {
                // A partial publication is replaced by a fully validated build below.
            }
            $this->storage->delete($part->path);
        }
        $this->dispatcher->checkpoint($run, ['stage' => __('Building :kind part :number', ['kind' => $part->kind, 'number' => $part->number])]);
        $files = $part->entries()->orderBy('position')->get()->map(fn(LibraryTransferEntry $entry): array => [
            'path' => $entry->path,
            'source' => $entry->source_path,
            'disk' => $entry->source_disk,
            'bytes' => $entry->bytes,
            'sha256' => $entry->sha256,
            'media_type' => $entry->media_type,
            'section' => $entry->section,
        ])->all();
        $disk->makeDirectory(dirname($part->path));
        self::space($this->bound($files), $disk->path(dirname($part->path)));
        $temporary = $run->directory() . '/build/' . $part->id . '.' . $run->generation . '.tmp';
        $disk->makeDirectory(dirname($temporary));
        $zip = new ZipArchive;
        if ($zip->open($disk->path($temporary), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create ZIP.');
        }
        try {
            $json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($json) > config('transfers.max_manifest_bytes')) {
                throw new RuntimeException('Manifest is too large; choose smaller archive parts.');
            }
            $zip->addFromString('manifest.json', $json);
            foreach ($files as $file) {
                $absolute = Storage::disk($file['disk'])->path($file['source']);
                if (! is_file($absolute) || hash_file('sha256', $absolute) !== $file['sha256']) {
                    throw new SourceChanged('Export source changed: ' . $file['path']);
                }
                if (! $zip->addFile($absolute, $file['path'])) {
                    throw new RuntimeException('Unable to add ZIP entry: ' . $file['path']);
                }
                // Images are already compressed; STORE makes the hard size bound predictable.
                $zip->setCompressionName($file['path'], ZipArchive::CM_STORE);
            }
        } catch (Throwable $exception) {
            // close() may also fail after a disappeared source; preserve the first cause.
            try {
                @$zip->close();
            } catch (Throwable) {
            }
            throw $exception;
        }
        if (! @$zip->close()) {
            foreach ($files as $file) {
                $source = Storage::disk($file['disk'])->path($file['source']);
                if (! is_file($source) || @hash_file('sha256', $source) !== $file['sha256']) {
                    throw new SourceChanged('Export source changed during finalization: ' . $file['path']);
                }
            }
            throw new RuntimeException('Unable to finalize ZIP.');
        }
        // ZipArchive reads addFile sources at close(), so check again after finalization.
        foreach ($files as $file) {
            $source = Storage::disk($file['disk'])->path($file['source']);
            if (! is_file($source) || hash_file('sha256', $source) !== $file['sha256']) {
                throw new SourceChanged('Export source changed during build: ' . $file['path']);
            }
        }
        $this->dispatcher->checkpoint($run, ['stage' => __('Validating :kind part :number', ['kind' => $part->kind, 'number' => $part->number])]);
        $this->inspect($disk->path($temporary), true);
        if ($run->settings['part_bytes'] !== null && $disk->size($temporary) > $run->settings['part_bytes']) {
            throw new RuntimeException('Final ZIP exceeds the configured part size.');
        }
        $bytes = $disk->size($temporary);
        $hash = hash_file('sha256', $disk->path($temporary));
        $this->storage->publish($run, $temporary, $part->path);
        DB::transaction(function () use ($run, $part, $bytes, $hash): void {
            $current = LibraryTransferRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== LibraryTransferRunStatus::Building || $current->generation !== $run->generation) {
                return;
            }
            $part->update(['status' => LibraryTransferPartStatus::Ready, 'bytes' => $bytes, 'sha256' => $hash]);
            $this->buildProgress($run, $part);
        });
    }

    private function buildProgress(LibraryTransferRun $run, LibraryTransferPart $part): void
    {
        DB::transaction(function () use ($run, $part): void {
            $ready = $run->parts()->where('status', LibraryTransferPartStatus::Ready)->count();
            if ($this->dispatcher->checkpoint($run, ['processed' => $ready, 'stage' => __('Built :kind part :number', ['kind' => $part->kind, 'number' => $part->number]), 'status' => $ready === (int) $run->total ? LibraryTransferRunStatus::Ready : LibraryTransferRunStatus::Building])) {
                $this->nextBuild($run);
            }
        });
    }

    private function nextBuild(LibraryTransferRun $run): void
    {
        if ($run->status !== LibraryTransferRunStatus::Building) {
            return;
        }

        $part = $run->parts()->where('status', LibraryTransferPartStatus::Pending)->orderBy('id')->first();
        if ($part !== null) {
            $this->dispatcher->checkpoint($run, [], LibraryTransferOperation::Build, $part->id);
        }
    }

    public function inspect(string $path, bool $verify = false, ?string $stage = null): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Enable the PHP zip extension before importing.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidArchive('Invalid ZIP archive.');
        }
        try {
            $stat = $zip->statName('manifest.json');
            if (! $stat || $stat['size'] > config('transfers.max_manifest_bytes') || ($stat['encryption_method'] ?? 0) !== 0 || $zip->numFiles > config('transfers.max_entries')) {
                throw new InvalidArchive('Missing or oversized manifest / too many ZIP entries.');
            }
            $manifest = self::decode($zip->getFromName('manifest.json'));
            $this->validateManifest($manifest);
            $declared = ['manifest.json' => true];
            foreach ($manifest['entries'] as $entry) {
                if (isset($declared[$entry['path']])) {
                    throw new InvalidArchive('Duplicate declared path.');
                }
                $declared[$entry['path']] = true;
            }
            $seen = [];
            $expanded = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if (! isset($declared[$name]) || isset($seen[$name]) || (($attributes >> 16) & 0170000) === 0120000 || ($entry['encryption_method'] ?? 0) !== 0 || config('transfers.max_compression_ratio') < $entry['size'] / max(1, $entry['comp_size'])) {
                    throw new InvalidArchive('Unsafe, duplicate, encrypted or undeclared ZIP entry: ' . $name);
                }
                $seen[$name] = true;
                if ($entry['size'] > PHP_INT_MAX - $expanded) {
                    throw new InvalidArchive('Expanded archive size exceeds supported limits.');
                }
                $expanded += $entry['size'];
            }
            if (count($seen) !== count($declared)) {
                throw new InvalidArchive('Declared ZIP entries are missing.');
            }
            if ($stage !== null) {
                Storage::disk('local')->makeDirectory($stage);
                self::space($expanded, Storage::disk('local')->path($stage));
            }
            foreach ($manifest['entries'] as $entry) {
                $stat = $zip->statName($entry['path']);
                if ($stat['size'] !== $entry['bytes']) {
                    throw new InvalidArchive('Entry size mismatch: ' . $entry['path']);
                }
                if (! $verify) {
                    continue;
                }
                $stream = $zip->getStream($entry['path']);
                if (! is_resource($stream)) {
                    throw new InvalidArchive('Cannot read archive entry.');
                }
                $hash = hash_init('sha256');
                $bytes = 0;
                $output = null;
                $temporary = null;
                $target = null;
                if ($stage !== null) {
                    $target = $stage . '/' . $entry['path'];
                    Storage::disk('local')->makeDirectory(dirname($target));
                    $temporary = Storage::disk('local')->path($target . '.checking');
                    $output = fopen($temporary, 'wb');
                    if (! is_resource($output)) {
                        fclose($stream);
                        throw new RuntimeException('Cannot stage archive entry.');
                    }
                }
                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, min(1048576, $entry['bytes'] - $bytes + 1));
                        if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                            throw new InvalidArchive('Cannot read archive entry.');
                        }
                        $bytes += strlen($chunk);
                        if ($bytes > $entry['bytes']) {
                            throw new InvalidArchive('Entry exceeds its declared size.');
                        }
                        hash_update($hash, $chunk);
                        if ($output && fwrite($output, $chunk) !== strlen($chunk)) {
                            throw new RuntimeException('Cannot stage archive entry.');
                        }
                    }
                } finally {
                    fclose($stream);
                    if (is_resource($output)) {
                        fclose($output);
                    }
                }
                if ($bytes !== $entry['bytes'] || hash_final($hash) !== $entry['sha256']) {
                    throw new InvalidArchive('Entry checksum mismatch: ' . $entry['path']);
                }
                if ($temporary !== null && $target !== null) {
                    if ($manifest['part']['kind'] === 'images' && ((new \finfo(FILEINFO_MIME_TYPE))->file($temporary) !== $entry['media_type'] || @getimagesize($temporary) === false)) {
                        throw new InvalidArchive('Invalid image content: ' . $entry['path']);
                    }
                    if (! rename($temporary, Storage::disk('local')->path($target))) {
                        throw new RuntimeException('Cannot publish validated archive entry.');
                    }
                }
            }

            return $manifest;
        } catch (ValidationException $exception) {
            throw new InvalidArchive($exception->getMessage(), previous: $exception);
        } finally {
            $zip->close();
        }
    }

    public static function decode(string|false $json): array
    {
        if ($json === false) {
            throw new InvalidArchive('Cannot read archive JSON.');
        }
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArchive('Invalid archive JSON.', previous: $exception);
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArchive('Archive JSON must be an object.');
        }

        return $decoded;
    }

    private function validateManifest(array $manifest): void
    {
        Validator::make($manifest, $this->rules(), $this->messages())->validate();
        $kind = $manifest['part']['kind'];
        $partCountExceeded = array_sum($manifest['parts']) > config('transfers.max_parts');
        $partCountMismatch = $manifest['part']['count'] !== $manifest['parts'][$kind];
        $imagesWithoutWorks = in_array('images', $manifest['scopes'], true)
            && ! in_array('works', $manifest['scopes'], true);
        $unexpectedImageParts = ! in_array('images', $manifest['scopes'], true)
            && $manifest['parts']['images'] !== 0;

        if ($partCountExceeded || $partCountMismatch || $imagesWithoutWorks || $unexpectedImageParts) {
            throw new InvalidArchive('Inconsistent part counts or scopes.');
        }
        if (in_array('works', $manifest['scopes'], true) && ($manifest['work_entry_format'] ?? null) !== WorkArchiveData::FORMAT) {
            throw new InvalidArchive('Unsupported or invalid archive layout: missing work entry format.');
        }
        foreach ($manifest['entries'] as $entry) {
            $this->validateManifestEntry($entry, $kind, $manifest['scopes']);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'format' => ['required', Rule::in(['dlsite-list'])],
            'schema_version' => ['required', 'integer:strict', Rule::in([1])],
            'archive_set_id' => ['required', 'uuid'],
            'exported_at' => ['required', 'date', 'regex:' . LibraryData::UTC_DATE_PATTERN],
            'work_entry_format' => ['sometimes', 'string'],
            'work_selection' => ['required', 'array:mode,count'],
            'part' => ['required', 'array:kind,number,count'],
            'parts' => ['required', 'array:data,images'],
            'work_selection.mode' => ['required', Rule::in(['all', 'selected'])],
            'work_selection.count' => ['required', 'integer:strict', 'min:0'],
            'scopes' => ['required', 'array', 'list', 'min:1'],
            'scopes.*' => ['required', 'distinct', Rule::in(['works', 'images', 'tag-library', 'options'])],
            'part.kind' => ['required', Rule::in(['data', 'images'])],
            'part.number' => ['required', 'integer:strict', 'min:1', 'lte:part.count'],
            'part.count' => ['required', 'integer:strict', 'min:1'],
            'parts.data' => ['required', 'integer:strict', 'min:1', 'max:' . config('transfers.max_parts')],
            'parts.images' => ['required', 'integer:strict', 'min:0', 'max:' . config('transfers.max_parts')],
            'entries' => ['required', 'array', 'list', 'min:1', 'max:' . config('transfers.max_entries')],
            'entries.*.path' => ['required', 'string', 'max:240'],
            'entries.*.sha256' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
            'entries.*.bytes' => ['required', 'integer:strict', 'min:0', 'max:' . (PHP_INT_MAX - 1)],
            'entries.*.media_type' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        $integerMessage = 'Manifest counts must be JSON integers and inventories must be JSON lists.';

        return [
            'schema_version.integer' => $integerMessage,
            'work_selection.count.integer' => $integerMessage,
            'part.number.integer' => $integerMessage,
            'part.count.integer' => $integerMessage,
            'parts.data.integer' => $integerMessage,
            'parts.images.integer' => $integerMessage,
            'entries.*.bytes.integer' => $integerMessage,
            'scopes.list' => $integerMessage,
            'entries.list' => $integerMessage,
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function fragmentRules(): array
    {
        return [
            'fragment' => ['required', 'integer:strict', 'min:1', 'lte:fragments'],
            'fragments' => ['required', 'integer:strict', 'min:1', 'max:' . config('transfers.max_entries')],
        ];
    }

    private function validateManifestEntry(array $entry, string $kind, array $scopes): void
    {
        if ($kind === 'data') {
            $section = $entry['section'] ?? null;
            $validWork = $this->validWorkEntry($entry);
            $validFragment = $this->validFragmentEntry($entry, $section);
            $validSection = in_array($section, $scopes, true);
            $validType = ($entry['media_type'] ?? null) === 'application/json';
            $withinSize = ($entry['bytes'] ?? PHP_INT_MAX) <= config('transfers.max_json_bytes');

            if ((! $validWork && ! $validFragment) || ! $validSection || ! $validType || ! $withinSize) {
                throw new InvalidArchive('Unsupported or invalid archive layout: invalid data entry.');
            }

            return;
        }

        if (! in_array('images', $scopes, true) || ! self::validImageEntry($entry)) {
            throw new InvalidArchive('Unsupported or invalid archive layout: invalid image entry.');
        }
    }

    private function validWorkEntry(array $entry): bool
    {
        if (($entry['section'] ?? null) !== 'works'
            || ! is_string($entry['path'] ?? null)
            || ! preg_match('/\Aworks\/(RJ\d+)\/work\.json\z/', $entry['path'], $match)
        ) {
            return false;
        }

        return ($entry['rj_code'] ?? null) === $match[1]
            && ! array_key_exists('fragment', $entry)
            && ! array_key_exists('fragments', $entry);
    }

    private function validFragmentEntry(array $entry, mixed $section): bool
    {
        if (
            ! in_array($section, ['tag-library', 'options'], true)
            || ! is_string($entry['path'] ?? null)
            || ! preg_match('/\A' . preg_quote((string) $section, '/') . '(?:\.part\d{4,})?\.json\z/', $entry['path'])
        ) {
            return false;
        }

        return array_key_exists('fragment', $entry)
            && array_key_exists('fragments', $entry)
            && Validator::make($entry, $this->fragmentRules())->passes();
    }

    public static function imageCategory(string $path): ?string
    {
        if (preg_match('/\\Aworks\\/RJ\\d+\\/images\\/(cover|sample_[1-9]\\d*)\\.(jpe?g|png|gif|webp|avif|bmp)\\z/', $path, $match)) {
            return $match[1] === 'cover' ? 'cover' : 'sample_images';
        }

        return null;
    }

    private static function validImageEntry(array $entry): bool
    {
        $category = self::imageCategory($entry['path']);
        $extension = pathinfo($entry['path'], PATHINFO_EXTENSION);
        if (
            $category === null || ($entry['section'] ?? null) !== $category
            || (LibraryData::IMAGE_TYPES[$entry['media_type']] ?? null) !== ($extension === 'jpeg' ? 'jpg' : $extension)
        ) {
            return false;
        }

        return true;
    }

    public static function space(int $bytes, string $directory): void
    {
        while (! is_dir($directory) && dirname($directory) !== $directory) {
            $directory = dirname($directory);
        }
        $free = disk_free_space($directory);
        if ($free !== false && $free < $bytes + config('transfers.disk_reserve_bytes')) {
            throw new RuntimeException('Not enough free disk space for this transfer.');
        }
    }
}
