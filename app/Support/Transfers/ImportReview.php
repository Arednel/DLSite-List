<?php

namespace App\Support\Transfers;

use App\Enums\LibraryImportDecision;
use App\Enums\LibraryImportItemStatus;
use App\Enums\LibraryTransferPartStatus;
use App\Models\Genre;
use App\Models\GenreGroup;
use App\Models\LibraryImportItem;
use App\Models\LibraryTransferEntry;
use App\Models\LibraryTransferRun;
use App\Models\Product;
use App\Support\GraphCycleValidator;
use App\Support\ProductContributorSync;
use App\Support\ProductGenreSync;
use App\Support\ProductImagePromotion;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

final class ImportReview
{
    public const CATEGORIES = [
        'works' => [
            'new_works',
            'titles',
            'descriptions',
            'series',
            'age',
            'circle',
            'maker',
            'scenario',
            'voice_actor',
            'illustration',
            'author',
            'tags',
            'cover',
            'sample_images',
            'notes',
            'score',
            'progress',
            'start_date',
            'end_date',
            'num_re_listen_times',
            're_listen_value',
            'priority',
            'custom_tags',
        ],
        'tag-library' => ['tags', 'groups', 'memberships', 'relationships'],
        'options' => ['options'],
    ];

    public const WORK_SCALAR_CATEGORIES = [
        'series' => ['details', 'series'],
        'age' => ['details', 'age_category'],
        'maker' => ['details', 'maker_id'],
        'notes' => ['details', 'notes'],
        'score' => ['listening', 'score'],
        'progress' => ['listening', 'progress'],
        'start_date' => ['listening', 'start_date'],
        'end_date' => ['listening', 'end_date'],
        'num_re_listen_times' => ['listening', 'num_re_listen_times'],
        're_listen_value' => ['listening', 're_listen_value'],
        'priority' => ['listening', 'priority'],
    ];

    public const WORK_CONTRIBUTOR_CATEGORIES = ['circle', 'scenario', 'voice_actor', 'illustration', 'author'];

    private bool $locking = false;

    /** @var array<string, GenreGroup>|null */
    private ?array $groupIndex = null;

    public function __construct(
        private readonly LibraryData $data,
        private readonly LibraryWorkValidator $workValidator,
        private readonly LibraryTagValidator $tagValidator,
        private readonly PortableOptions $options,
        private readonly ProductImagePromotion $promotion,
        private readonly ProductContributorSync $contributors,
        private readonly ProductGenreSync $genres,
        private readonly TransferJobDispatcher $dispatcher,
    ) {}

    public function setItemDecision(int $runId, int $itemId, ?LibraryImportDecision $decision): void
    {
        $this->withReviewableRun($runId, function (LibraryTransferRun $run) use ($itemId, $decision): void {
            $item = $run->items()->pending()->whereKey($itemId)->first();
            if (! $item) {
                return;
            }

            $effective = $this->decisionForCategory(
                $decision ?? LibraryImportDecision::tryFrom($run->settings['defaults'][$item->section][$item->category] ?? '') ?? LibraryImportDecision::Ignore,
                $item->category,
            );

            $item->update([
                'decision' => $effective,
                'decision_override' => $decision !== null,
            ]);
        });
    }

    public function setDefaultDecision(
        int $runId,
        LibraryImportDecision $decision,
        ?string $section,
        ?string $category,
    ): void {
        $all = $section === null && $category === null;
        if (! $all && ($section === null || $category === null || ! in_array($category, self::CATEGORIES[$section] ?? [], true))) {
            throw new RuntimeException('Invalid review category.');
        }

        $this->withReviewableRun($runId, function (LibraryTransferRun $run) use ($decision, $section, $category, $all): void {
            $defaults = $run->settings['defaults'] ?? [];
            $categories = $all ? self::CATEGORIES : [$section => [$category]];

            foreach ($categories as $sectionName => $sectionCategories) {
                foreach ($sectionCategories as $categoryName) {
                    $defaults[$sectionName][$categoryName] = $this->decisionForCategory($decision, $categoryName)->value;
                }
            }

            $run->update(['settings' => [...$run->settings, 'defaults' => $defaults]]);

            $items = $run->items()
                ->pending()
                ->where('decision_override', false)
                ->when(! $all, fn($query) => $query->forSectionCategory($section, $category));

            if ($all) {
                (clone $items)->where('category', '!=', 'new_works')->update(['decision' => $decision]);
                $items->where('category', 'new_works')->update([
                    'decision' => $this->decisionForCategory($decision, 'new_works'),
                ]);
            } else {
                $items->update(['decision' => $this->decisionForCategory($decision, $category)]);
            }
        });
    }

    private function decisionForCategory(LibraryImportDecision $decision, string $category): LibraryImportDecision
    {
        return $category === 'new_works' ? $decision->forNewWork() : $decision;
    }

    private function withReviewableRun(int $runId, Closure $callback): mixed
    {
        return Cache::lock('transfer-run-' . $runId, config('transfers.lock_seconds'))
            ->block(0, fn() => DB::transaction(function () use ($runId, $callback): mixed {
                $run = LibraryTransferRun::whereKey($runId)->lockForUpdate()->firstOrFail();
                if (! $run->reviewable()) {
                    throw new RuntimeException('Review choices can only be changed while the import is awaiting approval.');
                }

                return $callback($run);
            }));
    }

    public function stage(LibraryTransferRun $run, string $section, array $record, array $imageEntries, ?string $recordIdentity = null): void
    {
        $source = ['record_identity' => $recordIdentity];
        if ($section === 'works') {
            if ($run->settings['without_images'] ?? false) {
                unset($record['cover'], $record['sample_images']);
            }
            $record = $this->workValidator->validate($record);
            foreach (['cover', 'sample_images'] as $category) {
                if (! isset($record[$category])) {
                    continue;
                }
                if (! in_array('images', $run->settings['scopes'], true)) {
                    throw new InvalidArgumentException('Image scope was not declared.');
                }
                foreach ($record[$category]['files'] as $index => $file) {
                    $known = $imageEntries[$file['path'] ?? ''] ?? null;
                    $path = $file['path'] ?? null;
                    $validPath = is_string($path)
                        && str_starts_with($path, 'works/' . $record['rj_code'] . '/images/')
                        && TransferArchive::imageCategory($path) === $category;
                    $matchesInventory = $known
                        && $known['sha256'] === ($file['sha256'] ?? '')
                        && $known['bytes'] === ($file['bytes'] ?? null)
                        && $known['media_type'] === ($file['media_type'] ?? '');
                    if (! $validPath || ! $matchesInventory) {
                        throw new InvalidArgumentException('Work image reference does not match the validated image inventory.');
                    }
                    $record[$category]['files'][$index]['entry_id'] = $known['id'];
                }
            }
            $product = Product::find($record['rj_code']);
            $metadata = [...$source, 'title' => $record['titles']['work_name'], ...Arr::only($record, ['created_at', 'updated_at'])];
            if (! $product) {
                if ((isset($record['cover']) && ! $record['cover']['complete']) || (isset($record['sample_images']) && ! $record['sample_images']['complete'])) {
                    $metadata['warning'] = 'Incomplete image categories will be skipped when adding this work.';
                }
                $this->item($run, $section, 'new_works', $record['rj_code'], null, $record, $metadata);

                return;
            }
            $current = $this->data->work($product, isset($record['cover']) || isset($record['sample_images']));
            foreach (['titles', 'descriptions'] as $category) {
                foreach ($record[$category] ?? [] as $field => $value) {
                    $this->item($run, $section, $category, $product->id, $current[$category][$field] ?? null, $value, [...$metadata, 'field' => $field]);
                }
            }
            foreach (self::WORK_SCALAR_CATEGORIES as $category => [$group, $field]) {
                if (array_key_exists($field, $record[$group] ?? [])) {
                    $this->item($run, $section, $category, $product->id, $current[$group][$field] ?? null, $record[$group][$field], [...$metadata, 'field' => $field]);
                }
            }
            foreach (self::WORK_CONTRIBUTOR_CATEGORIES as $role) {
                if (array_key_exists($role, $record['contributors'] ?? [])) {
                    $this->item($run, $section, $role, $product->id, $current['contributors'][$role] ?? [], $record['contributors'][$role], [...$metadata, 'domain' => 'contributors', 'bucket' => $role]);
                }
            }
            $fetchedTags = Arr::only($record['tags'] ?? [], ['jp', 'en']);
            if ($fetchedTags !== []) {
                $this->item($run, $section, 'tags', $product->id, Arr::only($current['tags'], array_keys($fetchedTags)), $fetchedTags, [...$metadata, 'domain' => 'tags']);
            }
            if (array_key_exists('custom', $record['tags'] ?? [])) {
                $this->item($run, $section, 'custom_tags', $product->id, $current['tags']['custom'] ?? [], $record['tags']['custom'], [...$metadata, 'domain' => 'tags', 'bucket' => 'custom']);
            }
            foreach (['cover', 'sample_images'] as $category) {
                if (! array_key_exists($category, $record)) {
                    continue;
                }
                $this->item(
                    $run,
                    $section,
                    $category,
                    $product->id,
                    $this->imageBaseline($product, $category, $current[$category]),
                    $record[$category],
                    $metadata,
                    $record[$category]['complete'] ? null : 'Archive image category is incomplete.'
                );
            }
        } elseif ($section === 'options') {
            if (! is_string($record['key'] ?? null) || ! array_key_exists('value', $record)) {
                throw new InvalidArgumentException('Invalid option record.');
            }
            $this->options->validate($record['key'], $record['value']);
            $this->item($run, $section, 'options', $record['key'], $this->options->current($record['key']), $record['value'], $source);
        } else {
            $this->tagValidator->validate($record);
            $type = $record['type'];
            $value = $record['value'];
            $model = $type === 'tag' ? Genre::where('title_key', $record['key'])->first() : $this->group($record['key']);
            $domainValue = Arr::only($value, LibraryData::METADATA);
            $reviewMetadata = [...$source, 'archive_order' => $record['_order'] ?? 0, 'parents' => $value['parents'] ?? [], 'model_id' => $model?->id];
            $this->item($run, $section, $type === 'tag' ? 'tags' : 'groups', $record['key'], $model ? Arr::only($model->attributesToArray(), array_keys($domainValue)) : null, $domainValue, $reviewMetadata);
            if ($type === 'group') {
                $this->item($run, $section, 'memberships', $record['key'], $model ? $model->genres->pluck('title')->all() : [], $value['members'] ?? [], [...$source, 'title' => $value['title'], 'model_id' => $model?->id]);
            }
        }
    }

    public function item(LibraryTransferRun $run, string $section, string $category, string $key, mixed $baseline, mixed $incoming, array $metadata = [], ?string $error = null): LibraryImportItem
    {
        $identity = self::identityHash($section, $category, $key, $metadata);

        $recordIdentity = $metadata['record_identity'] ?? null;
        $collection = (bool) ($metadata['collection'] ?? false);
        $item = $run->items()->updateOrCreate(['identity_hash' => $identity], [
            'section' => $section,
            'category' => $category,
            'entity_key' => mb_substr($key, 0, 1024),
            'record_identity' => $recordIdentity,
            'baseline' => $baseline,
            'incoming' => $incoming,
            'metadata' => Arr::except($metadata, ['collection', 'record_identity']),
            'collection' => $collection,
            'status' => $error === null ? LibraryImportItemStatus::Pending : LibraryImportItemStatus::Unavailable,
            'decision' => LibraryImportDecision::Ignore,
            'error' => $error,
        ]);

        if ($category === 'new_works') {
            $previews = [];
            foreach (array_slice(self::CATEGORIES['works'], 1) as $workCategory) {
                $previews[$workCategory] = ImportValue::newWorkChanges($item, $workCategory);
            }
            $item->incoming_preview = ['new_work' => $previews];
            $item->baseline_preview = ['value' => null, 'image' => false, 'truncated' => false];
        } else {
            $item->baseline_preview = ImportValue::preview($item, 'baseline');
            $item->incoming_preview = ImportValue::preview($item, 'incoming');
        }
        $item->save();

        return $item;
    }

    public function group(string $key): ?GenreGroup
    {
        $this->groupIndex ??= GenreGroup::query()
            ->when($this->locking, fn($query) => $query->lockForUpdate())
            ->with(['genres' => fn($query) => $query->when($this->locking, fn($query) => $query->lockForUpdate())])
            ->get()
            ->keyBy(fn(GenreGroup $group): string => Genre::titleKey($group->title))
            ->all();

        return $this->groupIndex[$key] ?? null;
    }

    private function groupForItem(LibraryImportItem $item): ?GenreGroup
    {
        if ($id = $item->metadata['model_id'] ?? null) {
            $group = GenreGroup::whereKey($id)
                ->when($this->locking, fn($query) => $query->lockForUpdate())
                ->first();

            return $group && Genre::titleKey($group->title) === $item->entity_key ? $group : null;
        }

        $title = $item->category === 'memberships'
            ? ($item->metadata['title'] ?? null)
            : ($item->incoming['title'] ?? null);
        if (! is_string($title)) {
            return null;
        }
        $group = GenreGroup::where('title', $title)
            ->when($this->locking, fn($query) => $query->lockForUpdate())
            ->first();

        return $group && Genre::titleKey($group->title) === $item->entity_key ? $group : null;
    }

    public function groupBaseline(): array
    {
        return GenreGroup::query()->ordered()->when($this->locking, fn($query) => $query->lockForUpdate())->with(['genres' => fn($query) => $query->when($this->locking, fn($query) => $query->lockForUpdate())])->get()->map(fn($group) => [
            'key' => Genre::titleKey($group->title),
            'metadata' => Arr::only($group->attributesToArray(), [...LibraryData::METADATA, 'order']),
            'members' => $group->genres->pluck('title')->all(),
        ])->all();
    }

    public function relationships(array $keys, bool $lock = false): array
    {
        $result = DB::table('genre_relations as edges')
            ->join('genres as parent', 'parent.id', '=', 'edges.parent_genre_id')
            ->join('genres as child', 'child.id', '=', 'edges.child_genre_id')
            ->where(fn($query) => $query->whereIn('parent.title_key', $keys)->orWhereIn('child.title_key', $keys))
            ->when($lock, fn($query) => $query->lockForUpdate())
            ->get(['parent.title_key as parent_key', 'child.title_key as child_key'])
            ->mapWithKeys(fn($edge) => [self::edgeHash([$edge->parent_key, $edge->child_key]) => [$edge->parent_key, $edge->child_key]])->all();
        ksort($result);

        return array_values($result);
    }

    public function affectedWorkCountForRun(LibraryTransferRun $run, ?string $section = null, ?string $category = null): int
    {
        if (($section !== null && $section !== 'tag-library') || ($category !== null && $category !== 'relationships')) {
            return 0;
        }
        $children = [];
        foreach ($run->items()->pending()->forSectionCategory(category: 'relationships')->where('decision', '<>', LibraryImportDecision::Ignore)->lazyById(100) as $item) {
            if ($item->incoming && ! $item->baseline) {
                $children[] = $item->metadata['edge'][1];
            }
        }

        return Product::whereHas('genres', fn($query) => $query->whereIn('title_key', array_unique($children)))->count();
    }

    public function stageRelationships(LibraryTransferRun $run, array $keys, array $edges, ?string $error = null, bool $refresh = false): void
    {
        $current = $this->relationships($keys);
        $this->guardGraphSize([$keys, $edges, $current]);
        $incoming = array_fill_keys(array_map(self::edgeHash(...), $edges), true);
        $baseline = array_fill_keys(array_map(self::edgeHash(...), $current), true);
        $reviewEdges = collect([...$edges, ...$current])->unique(fn($edge) => self::edgeHash($edge))->values();
        $terminal = $refresh
            ? $run->items()->whereIn('identity_hash', $reviewEdges->map(fn($edge) => self::identityHash('tag-library', 'relationships', '', ['edge' => $edge]))->all())
            ->whereIn('status', [LibraryImportItemStatus::Applied, LibraryImportItemStatus::Ignored])->pluck('identity_hash')->flip()
            : collect();
        foreach ($reviewEdges as $edge) {
            $key = $edge[0] . ' → ' . $edge[1];
            $identity = self::identityHash('tag-library', 'relationships', $key, ['edge' => $edge]);
            if ($terminal->has($identity)) {
                continue;
            }
            $hash = self::edgeHash($edge);
            $item = $this->item($run, 'tag-library', 'relationships', $key, isset($baseline[$hash]), isset($incoming[$hash]), ['edge' => $edge], $error);
            if ($refresh) {
                $item->update(['decision_override' => true]);
            }
        }
        $this->dispatcher->checkpoint($run, ['settings' => [...$run->settings, 'relationship_scope' => ['keys' => $keys, 'hash' => LibraryData::hash($current)]]]);
    }

    private static function identityHash(string $section, string $category, string $key, array $metadata): string
    {
        $naturalIdentity = isset($metadata['edge'])
            ? ['edge', ...self::normalizedEdge($metadata['edge'])]
            : ['entity', $key, $metadata['field'] ?? null];

        return LibraryData::hash([$section, $category, $naturalIdentity, (bool) ($metadata['collection'] ?? false)]);
    }

    /** @return list<string> */
    private static function normalizedEdge(array $edge): array
    {
        return array_map(Genre::titleKey(...), $edge);
    }

    private static function edgeHash(array $edge): string
    {
        return LibraryData::hash(self::normalizedEdge($edge));
    }

    public function refreshRelationships(LibraryTransferRun $run): void
    {
        if (! $run->items()->forSectionCategory(category: 'relationships')
            ->whereIn('status', [LibraryImportItemStatus::Conflict, LibraryImportItemStatus::Failed])->exists()) {
            return;
        }
        $edges = $run->items()->where('category', 'relationships')->whereJsonContains('incoming', true)->get(['metadata'])->map(fn($item) => $item->metadata['edge'])->all();
        $this->stageRelationships($run, $run->settings['relationship_scope']['keys'], $edges, refresh: true);
    }

    public function applyRelationshipChoices(LibraryTransferRun $run): void
    {
        $query = $run->items()->pending()->forSectionCategory('tag-library', 'relationships');
        if (! (clone $query)->exists()) {
            return;
        }
        try {
            DB::transaction(function () use ($run, $query): void {
                $items = (clone $query)->orderBy('id')->lockForUpdate()->get();
                $selected = $items->where('decision', '<>', LibraryImportDecision::Ignore);
                if ($selected->isEmpty()) {
                    (clone $query)->update(['status' => LibraryImportItemStatus::Ignored]);

                    return;
                }
                // The complete graph is one atomic constraint, while choices remain per edge.
                Genre::orderBy('id')->lockForUpdate()->get(['id']);
                DB::table('genre_relations')->orderBy('parent_genre_id')->orderBy('child_genre_id')->lockForUpdate()->get();
                $scope = $run->settings['relationship_scope'];
                $current = $this->relationships($scope['keys'], true);
                if (LibraryData::hash($current) !== $scope['hash']) {
                    (clone $query)->where('decision', '<>', LibraryImportDecision::Ignore)->update(['status' => LibraryImportItemStatus::Conflict, 'error' => 'Tag relationships changed after review. Refresh conflicts before applying these choices.']);
                    (clone $query)->where('decision', LibraryImportDecision::Ignore)->update(['status' => LibraryImportItemStatus::Ignored]);

                    return;
                }
                $desired = [];
                foreach ($current as $edge) {
                    $desired[LibraryData::hash($edge)] = $edge;
                }
                foreach ($selected as $item) {
                    $edge = $item->metadata['edge'];
                    $hash = LibraryData::hash($edge);
                    if ($item->incoming) {
                        $desired[$hash] = $edge;
                    } elseif ($item->decision === LibraryImportDecision::Overwrite) {
                        unset($desired[$hash]);
                    }
                }
                $this->guardGraphSize([$scope['keys'], array_values($desired)]);
                $this->applyRelationships(['keys' => $scope['keys'], 'edges' => array_values($desired)], true);
                foreach ($items as $item) {
                    $item->update(['status' => $item->decision === LibraryImportDecision::Ignore ? LibraryImportItemStatus::Ignored : LibraryImportItemStatus::Applied, 'error' => null]);
                }
                $this->dispatcher->checkpoint($run, ['settings' => [...$run->settings, 'relationship_scope' => [...$scope, 'hash' => LibraryData::hash($this->relationships($scope['keys'], true))]]]);
            });
        } catch (InvalidArgumentException $exception) {
            (clone $query)->where('decision', '<>', LibraryImportDecision::Ignore)->update(['status' => LibraryImportItemStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
            (clone $query)->where('decision', LibraryImportDecision::Ignore)->update(['status' => LibraryImportItemStatus::Ignored]);
        }
    }

    public function guardGraphSize(array $data): void
    {
        if (count($data, COUNT_RECURSIVE) > config('transfers.max_entries') || strlen(json_encode($data, JSON_THROW_ON_ERROR)) > config('transfers.max_json_bytes')) {
            throw new InvalidArchive('Tag relationships exceed the configured graph safety limit.');
        }
    }

    public function current(LibraryImportItem $item, ?Product $product = null): mixed
    {
        $incoming = $item->incoming;
        if ($item->section === 'options') {
            return $this->options->current($item->entity_key, $this->locking);
        }
        if ($item->section === 'tag-library') {
            if ($item->category === 'relationships' && isset($item->metadata['edge'])) {
                $edge = $item->metadata['edge'];

                return in_array($edge, $this->relationships($edge), true);
            }
            if ($item->collection) {
                return $item->category === 'groups' ? $this->groupBaseline() : $this->relationships($incoming['keys']);
            }
            $model = $item->category === 'tags' ? Genre::where('title_key', $item->entity_key)->when($this->locking, fn($query) => $query->lockForUpdate())->first() : $this->groupForItem($item);

            return $item->category === 'memberships' ? ($model?->genres()->when($this->locking, fn($query) => $query->lockForUpdate())->pluck('title')->all() ?? []) : ($model ? Arr::only($model->attributesToArray(), array_keys($incoming)) : null);
        }
        $product ??= Product::whereKey($item->entity_key)->when($this->locking, fn($query) => $query->lockForUpdate())->first();
        if ($item->category === 'new_works') {
            return $product ? ['exists' => true] : null;
        }
        if (! $product) {
            return ['removed' => true];
        }
        if (isset($item->metadata['domain'])) {
            $current = $this->data->category($product, $item->metadata['domain'], $this->locking);

            return isset($item->metadata['bucket'])
                ? ($current[$item->metadata['bucket']] ?? [])
                : Arr::only($current, array_keys($incoming));
        }
        if (isset($item->metadata['field'])) {
            return $product->getAttribute($item->metadata['field']);
        }
        if (in_array($item->category, ['cover', 'sample_images'], true)) {
            return $this->imageBaseline($product, $item->category);
        }

        return Arr::only($this->data->category($product, $item->category, $this->locking), array_keys($incoming));
    }

    private function imageValue(array $category): array
    {
        return [
            'complete' => $category['complete'],
            'files' => Arr::select($category['files'], ['sha256', 'bytes', 'media_type']),
        ];
    }

    private function imageBaseline(Product $product, string $category, ?array $inventory = null): array
    {
        return [
            'references' => $category === 'cover' ? $product->work_image : $product->sample_images,
            'inventory' => $this->imageValue($inventory ?? $this->data->imageCategory($product, $category))
        ];
    }

    public function applyItem(LibraryImportItem $item): void
    {
        if ($item->section === 'works') {
            $this->applyWorkItems(collect([$item]));

            return;
        }
        if ($item->status !== LibraryImportItemStatus::Pending) {
            return;
        }
        if ($item->decision === LibraryImportDecision::Ignore) {
            $item->update(['status' => LibraryImportItemStatus::Ignored]);

            return;
        }
        try {
            $this->locking = true;
            $this->groupIndex = null;
            DB::transaction(function () use ($item): void {
                $locked = LibraryImportItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
                $item->setRawAttributes($locked->getAttributes(), true);
                if ($item->status !== LibraryImportItemStatus::Pending) {
                    return;
                }
                if ($item->section === 'tag-library') {
                    if ($item->collection) {
                        GenreGroup::orderBy('id')->lockForUpdate()->get();
                        DB::table('genre_group_genre')->orderBy('id')->lockForUpdate()->get();
                    } elseif ($item->category === 'tags') {
                        Genre::where('title_key', $item->entity_key)->lockForUpdate()->first();
                    } else {
                        $group = $this->groupForItem($item);
                        if ($group) {
                            $group->genres()->lockForUpdate()->get();
                        }
                    }
                }
                $current = $this->current($item);
                if (LibraryData::hash($current) !== LibraryData::hash($item->baseline)) {
                    $item->update(['status' => LibraryImportItemStatus::Conflict, 'error' => 'Local data changed after review. Refresh this conflict to review it again.']);

                    return;
                }
                if ($item->section === 'options') {
                    if (! ($item->metadata['missing'] ?? false) || $item->decision === LibraryImportDecision::Overwrite) {
                        $this->options->apply($item->entity_key, $this->options->combine($item->entity_key, $item->incoming, $item->decision->value, $current));
                    }
                } else {
                    $this->applyTags($item);
                }
                $item->update(['status' => LibraryImportItemStatus::Applied, 'error' => null]);
            });
        } catch (InvalidArgumentException $exception) {
            $item->update(['status' => LibraryImportItemStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
        } finally {
            $this->locking = false;
            $this->groupIndex = null;
        }
    }

    /** Apply all selected categories for one RJ code as one database/filesystem unit. */
    public function applyWorkItems(Collection $items): void
    {
        $items = $items->where('section', 'works')->where('status', LibraryImportItemStatus::Pending)->values();
        if ($items->isEmpty()) {
            return;
        }
        $owner = $items->first(fn(LibraryImportItem $item): bool => in_array($item->category, ['cover', 'sample_images'], true)) ?? $items->first();
        try {
            $this->locking = true;
            $this->groupIndex = null;
            $this->promotion->transaction(function () use ($items): void {
                $locked = LibraryImportItem::whereIn('id', $items->pluck('id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $items->each(function (LibraryImportItem $item) use ($locked): void {
                    if ($fresh = $locked->get($item->id)) {
                        $item->setRawAttributes($fresh->getAttributes(), true);
                    }
                });
                $pending = $items->where('status', LibraryImportItemStatus::Pending);
                $selected = $pending->where('decision', '<>', LibraryImportDecision::Ignore);
                $ignored = $pending->where('decision', LibraryImportDecision::Ignore);
                if ($selected->isEmpty()) {
                    LibraryImportItem::whereIn('id', $ignored->pluck('id'))->update(['status' => LibraryImportItemStatus::Ignored]);

                    return;
                }

                $rjCode = $pending->first()->entity_key;
                $product = Product::whereKey($rjCode)->lockForUpdate()->first();
                if ($product && $selected->contains(fn(LibraryImportItem $item): bool => in_array($item->category, ['tags', 'custom_tags'], true))) {
                    $pivots = DB::table('genre_product')->where('product_id', $product->id)->lockForUpdate()->pluck('id');
                    DB::table('genre_product_languages')->whereIn('genre_product_id', $pivots)->lockForUpdate()->get();
                }
                $stale = $selected->filter(fn(LibraryImportItem $item): bool => LibraryData::hash($this->current($item, $product)) !== LibraryData::hash($item->baseline));
                if ($stale->isNotEmpty()) {
                    LibraryImportItem::whereIn('id', $selected->pluck('id'))->update([
                        'status' => LibraryImportItemStatus::Conflict,
                        'error' => 'Another selected change for this work became stale. Refresh this work before applying it.',
                    ]);
                    LibraryImportItem::whereIn('id', $ignored->pluck('id'))->update(['status' => LibraryImportItemStatus::Ignored]);

                    return;
                }

                $ordered = $selected->sortBy(fn(LibraryImportItem $item): int => array_search($item->category, self::CATEGORIES['works'], true));
                foreach ($ordered as $item) {
                    $this->applyWork($item, $product);
                    $product = Product::whereKey($rjCode)->lockForUpdate()->first();
                    $item->update(['status' => LibraryImportItemStatus::Applied, 'error' => null]);
                }
                LibraryImportItem::whereIn('id', $ignored->pluck('id'))->update(['status' => LibraryImportItemStatus::Ignored]);
                $this->finalizeWorkTimestamps($pending->first()->run, $rjCode, $product);
            }, $owner);
        } catch (InvalidArgumentException $exception) {
            LibraryImportItem::whereIn('id', $items->where('decision', '<>', LibraryImportDecision::Ignore)->pluck('id'))->update([
                'status' => LibraryImportItemStatus::Failed,
                'error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
        } finally {
            $this->locking = false;
            $this->groupIndex = null;
        }
    }

    private function finalizeWorkTimestamps(LibraryTransferRun $run, string $rjCode, ?Product $product): void
    {
        $items = $run->items()->where('section', 'works')->where('entity_key', $rjCode)->orderBy('id')->lockForUpdate()->get();
        if (
            $items->isEmpty() || $items->contains(fn(LibraryImportItem $item): bool => $item->result['timestamps_finalized'] ?? false)
            || $items->contains(fn(LibraryImportItem $item): bool => $item->status === LibraryImportItemStatus::Pending)
        ) {
            return;
        }
        $eligible = $product && $items->every(fn(LibraryImportItem $item): bool => $item->status === LibraryImportItemStatus::Applied && $item->decision === LibraryImportDecision::Overwrite);
        if ($eligible) {
            $eligible = $items->every(function (LibraryImportItem $item) use ($product): bool {
                $current = $this->current($item, $product);
                $incoming = $item->incoming;
                if (in_array($item->category, ['cover', 'sample_images'], true)) {
                    $current = $current['inventory'];
                    $incoming = $this->imageValue($incoming);
                }

                return LibraryData::hash($current) === LibraryData::hash($incoming);
            });
        }
        if ($eligible) {
            $this->restoreTimestamps($product, $items->first()->metadata);
        }
        $first = $items->first();
        $first->update(['result' => [...($first->result ?? []), 'timestamps_finalized' => true]]);
    }

    private function applyWork(LibraryImportItem $item, ?Product $product): void
    {
        $value = $item->incoming;
        if ($item->category === 'new_works') {
            $product = new Product(['id' => $item->entity_key]);
            foreach (LibraryData::FIELDS as $category => $fields) {
                $product->fill(Arr::only($value[$category] ?? [], $fields));
            }
            $product->save();
            $this->contributors($product, $value['contributors'] ?? []);
            $this->tags($product, $value['tags'] ?? [], $item);
            foreach (['cover', 'sample_images'] as $category) {
                if ($value[$category]['complete'] ?? false) {
                    $this->images($product, $item, $category, $value[$category], false);
                }
            }
            $this->restoreTimestamps($product, $value);

            return;
        }
        $merge = $item->decision === LibraryImportDecision::Merge;
        if (isset($item->metadata['field'])) {
            $field = $item->metadata['field'];
            if (! $merge || $product->$field === null || $product->$field === '') {
                $product->setAttribute($field, $value);
                if ($product->isDirty()) {
                    $product->save();
                }
            }
        } elseif (($item->metadata['domain'] ?? null) === 'contributors') {
            $current = $this->data->category($product, 'contributors', true);
            $bucket = $item->metadata['bucket'];
            $current[$bucket] = $merge ? $this->union($current[$bucket] ?? [], $value) : $value;
            $this->contributors($product, $current);
        } elseif (($item->metadata['domain'] ?? null) === 'tags') {
            $current = $this->data->category($product, 'tags', true);
            if (isset($item->metadata['bucket'])) {
                $bucket = $item->metadata['bucket'];
                $value = [$bucket => $merge ? $this->union($current[$bucket] ?? [], $value) : $value];
            } elseif ($merge) {
                foreach ($value as $bucket => $titles) {
                    $value[$bucket] = $this->union($current[$bucket] ?? [], $titles);
                }
            }
            $this->tags($product, $value, $item);
        } else {
            $this->images($product, $item, $item->category, $value, $merge);
        }
    }

    private function union(array $local, array $incoming): array
    {
        return collect([...$local, ...$incoming])->unique(fn($title) => Genre::titleKey($title))->values()->all();
    }

    private function contributors(Product $product, array $values): void
    {
        $this->contributors->sync($product, $values, $product->maker_id);
        if (array_key_exists('circle', $values)) {
            $product->circle = $this->contributors->namesByRole($product)['circle'][0] ?? null;
            if ($product->isDirty()) {
                $product->save();
            }
        }
    }

    private function tags(Product $product, array $values, LibraryImportItem $item): void
    {
        $current = $this->data->category($product, 'tags', $this->locking);
        $values = array_replace($current, $values);
        $resolved = $this->resolveTagBuckets($values, $item);
        $this->genres->sync($product, ['jp' => $resolved['jp'], 'en' => $resolved['en']], $resolved['custom']);
    }

    /** @param array<string, list<string>> $buckets */
    private function resolveTagBuckets(array $buckets, LibraryImportItem $item): array
    {
        $titles = collect($buckets)->flatten()->map(fn($title): string => trim((string) $title))->filter();
        $normalized = $titles->unique(fn(string $title): string => Genre::titleKey($title))->mapWithKeys(fn(string $title): array => [Genre::titleKey($title) => $title]);
        $existing = Genre::query()->whereIn('title_key', $normalized->keys())->when($this->locking, fn($query) => $query->lockForUpdate())->get()->keyBy('title_key');
        $missing = $normalized->except($existing->keys());
        if ($missing->isNotEmpty()) {
            $now = now();
            foreach (
                array_chunk($missing->map(fn(string $title, string $key): array => [
                    'title' => $title,
                    'title_key' => $key,
                    'description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all(), config('transfers.database_batch_records')) as $chunk
            ) {
                Genre::query()->insertOrIgnore($chunk);
            }
            $existing = Genre::query()->whereIn('title_key', $normalized->keys())->when($this->locking, fn($query) => $query->lockForUpdate())->get()->keyBy('title_key');
            $pendingItems = $item->run->items()->pending()->forSectionCategory('tag-library', 'tags')
                ->whereIn('entity_key', $missing->keys())->get();
            foreach ($pendingItems as $pending) {
                if ($pending->baseline !== null) {
                    continue;
                }
                $tag = $existing->get($pending->entity_key);
                $pending->baseline = $tag ? Arr::only($tag->attributesToArray(), array_keys($pending->incoming ?? [])) : null;
                $pending->baseline_preview = ImportValue::preview($pending, 'baseline');
                $pending->save();
            }
        }

        return collect($buckets)->map(fn(array $bucket): array => collect($bucket)
            ->map(fn($title) => $existing->get(Genre::titleKey($title))?->id)
            ->filter()->unique()->values()->all())->all();
    }

    private function resolveTags(array $titles, LibraryImportItem $item): array
    {
        return $this->resolveTagBuckets(['tags' => $titles], $item)['tags'];
    }

    private function images(Product $product, LibraryImportItem $item, string $category, array $value, bool $merge): void
    {
        if (! $value['complete'] || ($item->run->settings['without_images'] ?? false)) {
            return;
        }
        $current = $this->data->imageCategory($product, $category);
        if (LibraryData::hash($this->imageValue($current)) === LibraryData::hash($this->imageValue($value))) {
            return;
        }
        if ($merge && $category === 'cover' && $current['files'] !== []) {
            return;
        }
        $files = $value['files'];
        $paths = $merge && $category === 'sample_images' ? ($product->sample_images ?? []) : [];
        $hashes = $merge ? array_column($current['files'], 'sha256') : [];
        $promotions = [];
        $number = 0;
        $entries = LibraryTransferEntry::query()
            ->whereIn('id', array_filter(array_column($files, 'entry_id')))
            ->where('library_transfer_run_id', $item->library_transfer_run_id)
            ->whereRelation('part', 'status', LibraryTransferPartStatus::Valid)
            ->get()
            ->keyBy('id');
        foreach ($paths as $path) {
            if (preg_match('/sample_(\\d+)(?:_|\\.)/', basename($path), $match)) {
                $number = max($number, (int) $match[1]);
            }
        }
        foreach ($files as $file) {
            if ($merge && in_array($file['sha256'], $hashes, true)) {
                continue;
            }
            $entry = $entries->get($file['entry_id'] ?? 0);
            if (
                ! $entry || ! Storage::disk($entry->source_disk)->exists($entry->source_path)
                || hash_file('sha256', Storage::disk($entry->source_disk)->path($entry->source_path)) !== $file['sha256']
            ) {
                throw new RuntimeException('Staged image is missing or changed.');
            }
            $destination = 'Works/' . $product->id . '/' . ($category === 'cover' ? 'cover' : 'sample_' . (++$number)) . '.' . pathinfo($file['path'], PATHINFO_EXTENSION);
            $paths[] = 'storage/' . $destination;
            $hashes[] = $file['sha256'];
            $promotions[] = ['source' => $entry->source_path, 'destination' => $destination, 'sha256' => $file['sha256']];
        }
        $attributes = $category === 'cover' ? ['work_image' => $paths[0] ?? null] : ['sample_images' => $paths];
        $this->promotion->promote($product, $promotions, $attributes, 'local');
    }

    private function applyTags(LibraryImportItem $item): void
    {
        $value = $item->incoming;
        $overwrite = $item->decision === LibraryImportDecision::Overwrite;
        if ($item->collection) {
            if ($item->category === 'groups') {
                if ($overwrite) {
                    foreach (GenreGroup::query()->ordered()->get() as $group) {
                        $position = array_search(Genre::titleKey($group->title), $value, true);
                        if ($position === false) {
                            $group->delete();
                        } else {
                            $group->update(['order' => $position + 1]);
                        }
                    }
                }
            } else {
                $this->applyRelationships($value, $overwrite);
            }

            return;
        }
        if ($item->category === 'memberships') {
            $group = $this->groupForItem($item);
            if (! $group) {
                throw new InvalidArgumentException('Approve the group addition before importing its memberships.');
            }
            $titles = $overwrite ? $value : $this->union($group->genres->pluck('title')->all(), $value);
            $ids = $this->resolveTags($titles, $item);
            $group->genres()->sync(collect($ids)->mapWithKeys(fn($id, $index) => [$id => ['order' => $index + 1]])->all());

            return;
        }
        $model = $item->category === 'tags' ? Genre::where('title_key', $item->entity_key)->first() : $this->groupForItem($item);
        if (! $model) {
            $model = $item->category === 'tags' ? new Genre : new GenreGroup;
            if ($item->category === 'groups' && $overwrite) {
                $model->order = ($item->metadata['archive_order'] ?? 0) + 1;
            }
        }
        foreach ($value as $field => $part) {
            if (! $model->exists || $overwrite || $model->$field === null || $model->$field === '') {
                $model->$field = $part;
            }
        }
        $model->save();
        if ($item->category === 'groups' && ! ($item->metadata['model_id'] ?? null)) {
            $metadata = [...$item->metadata, 'model_id' => $model->id];
            $item->update(['metadata' => $metadata]);
            $membership = $item->run->items()->where('section', 'tag-library')->where('category', 'memberships')
                ->where('entity_key', $item->entity_key)->whereNull('metadata->model_id')->first();
            if ($membership) {
                $membership->update(['metadata' => [...$membership->metadata, 'model_id' => $model->id]]);
            }
        }
        $this->groupIndex = null;
    }

    private function applyRelationships(array $value, bool $overwrite): void
    {
        $included = $value['keys'];
        $edges = DB::table('genre_relations')->lockForUpdate()->get()->map(fn($row) => [(int) $row->parent_genre_id, (int) $row->child_genre_id])->all();
        $tags = Genre::lockForUpdate()->get()->keyBy('title_key');
        $this->guardGraphSize([$included, $edges, $value['edges']]);
        $oldEdges = $edges;
        foreach ($value['edges'] as [$parent, $child]) {
            if (! isset($tags[$parent], $tags[$child])) {
                throw new InvalidArgumentException('Approve referenced tag additions before importing relationships.');
            }
        }
        $includedIds = collect($included)->map(fn(string $key): int => (int) $tags[$key]->id)->all();
        $includedLookup = array_fill_keys($includedIds, true);
        if ($overwrite) {
            $edges = array_values(array_filter($edges, fn($edge) => ! isset($includedLookup[$edge[0]]) && ! isset($includedLookup[$edge[1]])));
        }
        foreach ($value['edges'] as [$parent, $child]) {
            $edges[] = [$tags[$parent]->id, $tags[$child]->id];
        }
        $this->validateGraph($edges);
        $oldKeys = array_fill_keys(array_map(fn($edge) => LibraryData::hash($edge), $oldEdges), true);
        $newEdges = array_filter($edges, fn($edge) => ! isset($oldKeys[LibraryData::hash($edge)]));
        $children = array_column($newEdges, 1);
        Product::whereHas('genres', fn($q) => $q->whereIn('genres.id', $children))->orderBy('id')->lockForUpdate()->get(['id']);
        if ($overwrite) {
            DB::table('genre_relations')->whereIn('parent_genre_id', $includedIds)->orWhereIn('child_genre_id', $includedIds)->delete();
        }
        $insertEdges = $overwrite
            ? array_values(array_filter($edges, fn($edge) => isset($includedLookup[$edge[0]]) || isset($includedLookup[$edge[1]])))
            : array_values($newEdges);
        $timestamp = now();
        foreach (array_chunk($insertEdges, config('transfers.database_batch_records')) as $chunk) {
            DB::table('genre_relations')->insertOrIgnore(array_map(fn($edge) => [
                'parent_genre_id' => $edge[0],
                'child_genre_id' => $edge[1],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $chunk));
        }
        $this->genres->syncParentsForProductsWithAny($children);
    }

    public function validateGraph(array $edges): void
    {
        if (! GraphCycleValidator::isAcyclic($edges)) {
            throw new InvalidArgumentException('Parent/child tag relationships cannot contain a cycle.');
        }
    }

    private function restoreTimestamps(Product $product, array $values): void
    {
        // Date casting requires timestamps enabled while assigning RFC 3339 strings.
        $product->forceFill(Arr::only($values, ['created_at', 'updated_at']));
        Product::withoutTimestamps(fn() => $product->save());
    }
}
