<?php

namespace App\Support\Refetch;

use App\Enums\DlsiteLanguage;
use App\Enums\ProductContributorRole;
use App\Enums\ProductFormat;
use App\Enums\RefetchCategory;
use App\Jobs\FetchProductWorkJob;
use App\Models\Genre;
use App\Models\Option;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\DLSite\DLSiteWorkData;
use App\Support\DLSite\DLSiteWorkFetcher;
use App\Support\LibraryMutationLock;
use App\Support\ProductContributorSync;
use App\Support\ProductGenreSync;
use App\Support\LocalizedMetadataField;
use App\Support\ProductImagePromotion;
use Closure;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

final class RefetchService
{
    public const FETCH_PROCESS_TIMEOUT_SECONDS = 590;

    public const ACTION_INHERIT = RefetchWorkResult::ACTION_INHERIT;

    public const ACTION_IGNORE = RefetchWorkResult::ACTION_IGNORE;

    public const ACTION_OVERWRITE = RefetchWorkResult::ACTION_OVERWRITE;

    public const ACTION_DETAILED = RefetchWorkResult::ACTION_DETAILED;

    public const TAG_STALE_MOVE_TO_CUSTOM = 'move_to_custom';

    public const TAG_STALE_REMOVE = 'remove';

    public const TAG_ADDED_ADD = 'add_as_fetched';

    public const TAG_ADDED_IGNORE = 'ignore';

    public const TAG_CUSTOM_PROMOTE = 'promote_to_fetched';

    public const TAG_CUSTOM_KEEP = 'keep_custom';

    public function __construct(
        private readonly DLSiteWorkFetcher $fetcher,
        private readonly RefetchDiffBuilder $diffBuilder,
        private readonly ProductGenreSync $genreSync,
        private readonly ProductContributorSync $contributorSync,
        private readonly LibraryMutationLock $mutationLock,
        private readonly ProductImagePromotion $imagePromotion,
    ) {}

    /**
     * @param  list<string>  $productIds
     */
    public function createRun(array $productIds, bool $checkImages): RefetchRun
    {
        return $this->mutationLock->run(fn(): RefetchRun => DB::transaction(
            fn(): RefetchRun => $this->createPendingRun($productIds, $checkImages),
        ));
    }

    /**
     * @param  list<string>  $productIds
     */
    public function startRun(array $productIds, bool $checkImages): RefetchRun
    {
        $languages = array_column(
            Option::effectiveDlsiteMetadataFetchLanguages(),
            'value',
        );

        return $this->mutationLock->run(fn(): RefetchRun => DB::transaction(function () use ($productIds, $checkImages, $languages): RefetchRun {
            $run = $this->createPendingRun($productIds, $checkImages);
            $batch = Bus::batch(
                collect($productIds)
                    ->map(fn(string $productId): FetchProductWorkJob => new FetchProductWorkJob($run->getKey(), $productId, $languages))
                    ->all(),
            )
                ->name("Refetch works #{$run->getKey()}")
                ->allowFailures()
                ->dispatch();

            $run->forceFill(['batch_id' => $batch->id])->save();

            return $run;
        }));
    }

    /**
     * @param  list<string>  $productIds
     */
    private function createPendingRun(array $productIds, bool $checkImages): RefetchRun
    {
        $run = RefetchRun::query()->create([
            'status' => RefetchRun::STATUS_RUNNING,
            'check_images' => $checkImages,
            'resolved_tabs' => [],
            'total_count' => count($productIds),
            'processed_count' => 0,
            'fetched_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
        ]);

        $run->results()->createMany(
            collect($productIds)
                ->map(fn(string $productId): array => [
                    'product_id' => $productId,
                    'status' => RefetchWorkResult::STATUS_PENDING,
                    'changes' => [],
                    'decisions' => [],
                    'warnings' => [],
                ])
                ->all(),
        );

        return $run;
    }

    /** @param list<string> $languages */
    public function fetchAndRecordResult(RefetchWorkResult $result, array $languages): void
    {
        $product = Product::query()->find($result->product_id);

        if (! $product) {
            $this->recordFailedResult($result, 'refetch.work_removed');

            return;
        }

        $run = $result->run()->firstOrFail();
        $stagedWorkPath = $this->stagedWorkPath($run, $result->product_id);
        $jsonPath = "{$stagedWorkPath}.json";
        $imageDirectory = $run->check_images
            ? $stagedWorkPath
            : null;
        try {
            $fetch = $this->fetcher->fetch(
                $result->product_id,
                Storage::disk('local')->path($jsonPath),
                $imageDirectory === null ? null : Storage::disk('public')->path($imageDirectory),
                self::FETCH_PROCESS_TIMEOUT_SECONDS,
                $languages,
            );

            $warnings = [];

            if (in_array('cover.jpg', $fetch->failedImages, true)) {
                $warnings[] = [
                    'key' => 'refetch.image.cover_download_failed',
                    'replace' => [],
                ];
            }

            $failedSamples = collect($fetch->failedImages)
                ->filter(fn(string $filename): bool => str_starts_with($filename, 'sample_'))
                ->values()
                ->all();

            if ($failedSamples !== []) {
                $warnings[] = [
                    'key' => 'refetch.image.sample_download_failed',
                    'replace' => ['images' => implode(', ', $failedSamples)],
                ];
            }

            $result->forceFill([
                'status' => RefetchWorkResult::STATUS_FETCHED,
                'changes' => $this->diffBuilder->build($product, $fetch, $imageDirectory),
                'warnings' => $warnings,
                'error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $this->recordFailedResult($result, $this->cleanErrorMessage($exception));

            return;
        }

        $this->refreshRunProgress($run);
    }

    public function recordFailedResult(RefetchWorkResult $result, string $error): void
    {
        $updated = RefetchWorkResult::query()
            ->whereKey($result->getKey())
            ->where('status', RefetchWorkResult::STATUS_PENDING)
            ->update([
                'status' => RefetchWorkResult::STATUS_FAILED,
                'error' => $error,
            ]);

        if ($updated === 0) {
            return;
        }

        $result->refresh();
        $this->refreshRunProgress($result->run()->firstOrFail());
    }

    public function refreshRunProgress(RefetchRun $run): void
    {
        $run->loadCount([
            'results as pending_results_count' => fn(Builder $query) => $query
                ->where('status', RefetchWorkResult::STATUS_PENDING),
            'results as fetched_results_count' => fn(Builder $query) => $query
                ->where('status', RefetchWorkResult::STATUS_FETCHED),
            'results as failed_results_count' => fn(Builder $query) => $query
                ->where('status', RefetchWorkResult::STATUS_FAILED),
        ]);

        $pending = (int) $run->pending_results_count;
        $fetched = (int) $run->fetched_results_count;
        $failed = (int) $run->failed_results_count;
        $updates = [
            'processed_count' => $fetched + $failed,
            'fetched_count' => $fetched,
            'failed_count' => $failed,
        ];

        if ($run->isActive() && $pending === 0) {
            $run->load('results');
            $updates['status'] = RefetchRun::STATUS_REVIEW;
            $updates['completed_at'] = now();
            $updates['resolved_tabs'] = collect(RefetchCategory::cases())
                ->reject(fn(RefetchCategory $category): bool => $run->results
                    ->contains(fn(RefetchWorkResult $result): bool => $result->hasChangesFor($category)))
                ->map->value
                ->values()
                ->all();
        }

        $run->forceFill($updates)->save();

        if ($run->isReview() && $run->cancelled_at === null && $this->allTabsResolved($run)) {
            $this->finalizeAppliedRun($run);
        }
    }

    public function cancelRun(RefetchRun $run): bool
    {
        $run->refresh();

        if ($run->isCancelling()) {
            return true;
        }

        if (! $run->canBeCancelled()) {
            return false;
        }

        $run->forceFill([
            'status' => RefetchRun::STATUS_CANCELLING,
            'cancelled_at' => $run->cancelled_at ?? now(),
        ])->save();

        if ($run->batch_id !== null) {
            Bus::findBatch($run->batch_id)?->cancel();
        }

        $this->refreshRunProgress($run);

        return true;
    }

    /**
     * @param  array<string, array<string, string>>  $actions
     * @param  array<string, array<string, string>>  $tagActions
     */
    public function applyTab(
        RefetchRun $run,
        RefetchCategory $category,
        string $globalAction,
        array $actions = [],
        array $tagActions = [],
    ): void {
        $this->withLifecycleLock(function () use (
            $actions,
            $category,
            $globalAction,
            $run,
            $tagActions,
        ): void {
            $this->ensureApplicable($run);

            if ($run->tabResolved($category)) {
                throw new RuntimeException('refetch.tab_resolved');
            }

            $resolvedTabs = $run->resolved_tabs ?? [];
            $fetchedResults = $this->fetchedResults($run);
            $this->applyCategory($fetchedResults, $category, $globalAction, $actions, $tagActions);
            $this->markTabResolved($run, $category);

            if ($this->allTabsResolved($run)) {
                $this->finalizeOrRestoreResolvedTabs($run, $resolvedTabs, $fetchedResults);
            }
        });
    }

    /**
     * @param  array<string, string>  $globalActions
     * @param  array<string, array<string, array<string, string>>>  $actions
     * @param  array<string, array<string, string>>  $tagActions
     */
    public function applyAll(
        RefetchRun $run,
        array $globalActions,
        array $actions = [],
        array $tagActions = [],
    ): void {
        $this->withLifecycleLock(function () use (
            $actions,
            $globalActions,
            $run,
            $tagActions,
        ): void {
            $this->ensureApplicable($run);
            $resolvedTabs = $run->resolved_tabs ?? [];
            $fetchedResults = $this->fetchedResults($run);

            foreach (RefetchCategory::cases() as $category) {
                if ($run->tabResolved($category)) {
                    continue;
                }

                $this->applyCategory(
                    $fetchedResults,
                    $category,
                    $globalActions[$category->value] ?? self::ACTION_IGNORE,
                    $actions[$category->value] ?? [],
                    $category === RefetchCategory::Tags ? $tagActions : [],
                );
            }

            $run->forceFill(['resolved_tabs' => RefetchCategory::values()])->save();
            $this->finalizeOrRestoreResolvedTabs($run, $resolvedTabs, $fetchedResults);
        });
    }

    public function rejectOrFinish(RefetchRun $run): void
    {
        $this->withLifecycleLock(function () use ($run): void {
            $this->ensureApplicable($run);
            $run->load('results');

            if (! $run->hasAppliedDecisions()) {
                $run->forceFill([
                    'status' => RefetchRun::STATUS_REJECTED,
                    'rejected_at' => now(),
                ])->save();

                return;
            }

            $resolvedTabs = $run->resolved_tabs ?? [];
            $fetchedResults = $this->fetchedResults($run);

            foreach (RefetchCategory::cases() as $category) {
                if ($run->tabResolved($category)) {
                    continue;
                }

                $this->applyCategory($fetchedResults, $category, self::ACTION_IGNORE);
            }

            $run->forceFill(['resolved_tabs' => RefetchCategory::values()])->save();
            $this->finalizeOrRestoreResolvedTabs($run, $resolvedTabs, $fetchedResults);
        });
    }

    /**
     * @param  EloquentCollection<int, RefetchWorkResult>  $results
     */
    private function applyCategory(
        EloquentCollection $results,
        RefetchCategory $category,
        string $globalAction,
        array $actions = [],
        array $tagActions = [],
    ): void {
        $results->each(function (RefetchWorkResult $result) use (
            $category,
            $globalAction,
            $actions,
            $tagActions,
        ): void {
            $changes = $result->changesFor($category);

            if ($changes === []) {
                return;
            }

            $this->imagePromotion->transaction(function () use ($result, $category, $changes, $actions, $globalAction, $tagActions): void {
                $decisions = $result->decisions ?? [];

                if ($category === RefetchCategory::Tags) {
                    $change = $changes['tags'] ?? null;

                    if (! is_array($change)) {
                        return;
                    }

                    $resultActions = $actions[$result->getKey()] ?? [];
                    $localeActions = $result->resolvedTagActions(
                        is_array($resultActions) ? $resultActions : [],
                        $globalAction,
                    );

                    $resultTagActions = $tagActions[(string) $result->getKey()] ?? [];
                    $changed = $this->applyLocalizedTagChoices(
                        $result,
                        $change,
                        $localeActions,
                        $resultTagActions,
                    );
                    $decisions[$category->value]['tags'] = [
                        'action' => 'per_locale',
                        'changed' => $changed,
                        'locales' => collect($localeActions)
                            ->map(fn(string $action): array => ['action' => $action])
                            ->all(),
                        'tag_actions' => $resultTagActions,
                    ];

                    $result->forceFill(['decisions' => $decisions])->save();

                    return;
                }

                foreach ($changes as $field => $change) {
                    $action = $this->resolvedAction(
                        data_get($actions, "{$result->getKey()}.{$field}"),
                        $globalAction,
                    );
                    $changed = $action === self::ACTION_OVERWRITE
                        ? $this->applyChange($result, $category, $field, $change)
                        : false;

                    $decisions[$category->value][$field] = [
                        'action' => $action,
                        'changed' => $changed,
                        'tag_actions' => null,
                    ];
                }

                $result->forceFill(['decisions' => $decisions])->save();
            }, $result);
        });
    }

    private function applyChange(
        RefetchWorkResult $result,
        RefetchCategory $category,
        string $field,
        array $change,
    ): bool {
        $product = $result->product;

        if (! $product) {
            return false;
        }

        if ($category === RefetchCategory::Cover) {
            $destination = "Works/{$product->getKey()}/cover.jpg";

            return $this->imagePromotion->promote(
                $product,
                [[
                    'source' => $change['staged_path'],
                    'destination' => $destination,
                ]],
                ['work_image' => "storage/{$destination}"],
            );
        }

        if ($category === RefetchCategory::SampleImages) {
            $promotions = [];
            $paths = [];

            foreach (array_values($change['staged_paths']) as $index => $stagedPath) {
                $destination = "Works/{$product->getKey()}/sample_" . ($index + 1) . '.jpg';
                $promotions[] = [
                    'source' => $stagedPath,
                    'destination' => $destination,
                ];
                $paths[] = "storage/{$destination}";
            }

            return $this->imagePromotion->promote(
                $product,
                $promotions,
                ['sample_images' => $paths],
            );
        }

        if ($category === RefetchCategory::Titles) {
            $parsed = LocalizedMetadataField::parse($field);
            if ($parsed === null || $parsed['attribute'] !== LocalizedMetadataField::TITLE) {
                return false;
            }

            $title = $change['new'] ?? null;
            if ($parsed['language'] === DlsiteLanguage::Japanese) {
                if (! is_string($title)) {
                    return false;
                }

                $product->work_name = $title;

                if (! $product->isDirty('work_name')) {
                    return false;
                }

                $product->save();

                return true;
            }

            return $product->syncLocalization(
                $parsed['language'],
                ['title' => blank($title) ? null : $title],
            );
        }

        if ($category === RefetchCategory::Descriptions) {
            $parsed = LocalizedMetadataField::parse($field);
            if ($parsed === null || $parsed['attribute'] !== LocalizedMetadataField::DESCRIPTION) {
                return false;
            }

            if ($parsed['language'] !== DlsiteLanguage::Japanese) {
                $description = $change['new'] ?? null;

                return $product->syncLocalization(
                    $parsed['language'],
                    ['description' => blank($description) ? null : $description],
                );
            }
        }

        return DB::transaction(function () use ($product, $category, $field, $change): bool {
            if (in_array($category, [
                RefetchCategory::Descriptions,
                RefetchCategory::Series,
                RefetchCategory::Age,
                RefetchCategory::SiteId,
                RefetchCategory::Publisher,
                RefetchCategory::Brand,
                RefetchCategory::ProductFormat,
                RefetchCategory::AnnouncementDate,
                RefetchCategory::Rating,
            ], true)) {
                $value = $change['new'];

                if ($category === RefetchCategory::ProductFormat) {
                    $value = ProductFormat::normalizeStoredValues([
                        ...ProductFormat::normalizeDlsiteValues(is_array($value) ? $value : [$value]),
                        ...ProductFormat::customStoredValues(is_array($product->product_format) ? $product->product_format : []),
                    ]);
                    $value = $value === [] ? null : $value;
                }

                $attributes = $category === RefetchCategory::Rating
                    ? $value
                    : [$field => $value];

                $product->forceFill($attributes);

                if (! $product->isDirty(array_keys($attributes))) {
                    return false;
                }

                $product->save();

                return true;
            }

            if ($category === RefetchCategory::Maker) {
                $product->forceFill(['maker_id' => $change['new']]);
                $productChanged = $product->isDirty('maker_id');

                if ($productChanged) {
                    $product->save();
                }

                $circleNames = $this->contributorSync->namesByRole($product)[ProductContributorRole::Circle->value] ?? [];
                $contributorsChanged = $this->contributorSync->syncRole(
                    $product,
                    ProductContributorRole::Circle,
                    $circleNames,
                    $change['new'],
                );

                return $productChanged || $contributorsChanged;
            }

            if ($role = $category->contributorRole()) {
                $names = is_array($change['new']) ? $change['new'] : [];
                $changed = $this->contributorSync->syncRole(
                    $product,
                    $role,
                    $names,
                    $role === ProductContributorRole::Circle ? $product->maker_id : null,
                );

                if ($role === ProductContributorRole::Circle) {
                    $product->forceFill(['circle' => $names[0] ?? null]);

                    if ($product->isDirty('circle')) {
                        $product->save();
                        $changed = true;
                    }
                }

                return $changed;
            }

            return false;
        });
    }

    /**
     * @param  array<string, string>  $localeActions
     * @param  array<string, mixed>  $tagActions
     */
    private function applyLocalizedTagChoices(
        RefetchWorkResult $result,
        array $change,
        array $localeActions,
        array $tagActions,
    ): bool {
        $product = $result->product;

        if (! $product) {
            return false;
        }

        $hasActionableLocale = collect($localeActions)->contains(
            fn(string $action): bool => in_array($action, [self::ACTION_OVERWRITE, self::ACTION_DETAILED], true),
        );
        if (! $hasActionableLocale) {
            return false;
        }

        $incomingByLocale = is_array(data_get($change, 'new.fetched'))
            ? data_get($change, 'new.fetched')
            : [];
        $details = is_array($change['details'] ?? null) ? $change['details'] : [];
        $currentFetchedIdsByLocale = $this->genreSync->currentFetchedGenreIdsByLanguage($product);
        $existingCustom = $product->customGenres()->pluck('genres.title')->all();
        $custom = $existingCustom;
        $detailedLocales = collect($localeActions)
            ->filter(fn(string $action): bool => $action === self::ACTION_DETAILED)
            ->keys()
            ->all();
        $customToFetched = $result->promotableCustomTags($localeActions);
        $promoteCustom = ($tagActions['custom_to_fetched'] ?? self::TAG_CUSTOM_PROMOTE) === self::TAG_CUSTOM_PROMOTE;

        if ($promoteCustom) {
            $custom = $this->tagDiff($existingCustom, $customToFetched);
        }

        $desiredFetchedTitles = [];
        foreach ($localeActions as $locale => $action) {
            if (! in_array($action, [self::ACTION_OVERWRITE, self::ACTION_DETAILED], true)) {
                continue;
            }

            $tags = $incomingByLocale[$locale] ?? [];

            $blockedCustom = $existingCustom;

            if ($action === self::ACTION_DETAILED) {
                $localeTagActions = data_get($tagActions, "locales.{$locale}", []);

                if (($localeTagActions['added'] ?? self::TAG_ADDED_ADD) === self::TAG_ADDED_IGNORE) {
                    $tags = $this->tagDiff($tags, $details['added'][$locale] ?? []);
                }

                if ($promoteCustom) {
                    $blockedCustom = $this->tagDiff(
                        $existingCustom,
                        $details['custom_to_fetched'][$locale] ?? [],
                    );
                } else {
                    $tags = $this->tagDiff($tags, $details['custom_to_fetched'][$locale] ?? []);
                }
            }

            // Overwrite never promotes an existing Custom tag implicitly. Detailed review
            // promotes only the explicitly reviewed Custom -> Fetched overlap for this locale.
            $desiredFetchedTitles[$locale] = $this->tagDiff($tags, $blockedCustom);
        }

        $preservedFetchedTags = $this->fetchedTagTitlesForUnchangedLocales(
            $currentFetchedIdsByLocale,
            array_keys($desiredFetchedTitles),
        );
        $finalFetchedTagLists = [
            ...array_values($desiredFetchedTitles),
            $preservedFetchedTags,
        ];

        foreach ($detailedLocales as $locale) {
            $localeTagActions = data_get($tagActions, "locales.{$locale}", []);

            if (($localeTagActions['stale'] ?? self::TAG_STALE_MOVE_TO_CUSTOM) !== self::TAG_STALE_MOVE_TO_CUSTOM) {
                continue;
            }

            $custom = array_merge(
                $custom,
                $this->tagDiff($details['stale'][$locale] ?? [], ...$finalFetchedTagLists),
            );
        }

        foreach ($desiredFetchedTitles as $locale => $tags) {
            $currentFetchedIdsByLocale[$locale] = Genre::resolveIdsFromTitles($tags);
        }

        return $this->genreSync->sync(
            $product,
            $currentFetchedIdsByLocale,
            Genre::resolveIdsFromTitles($custom),
        );
    }

    /**
     * @param  array<string, list<int>>  $currentFetchedIdsByLocale
     * @param  list<string>  $changedLocales
     * @return list<string>
     */
    private function fetchedTagTitlesForUnchangedLocales(
        array $currentFetchedIdsByLocale,
        array $changedLocales,
    ): array {
        $genreIds = collect($currentFetchedIdsByLocale)
            ->except($changedLocales)
            ->flatten()
            ->unique()
            ->values();

        if ($genreIds->isEmpty()) {
            return [];
        }

        return Genre::query()
            ->whereKey($genreIds->all())
            ->pluck('title')
            ->all();
    }

    private function resolvedAction(?string $action, string $globalAction): string
    {
        $allowed = [self::ACTION_IGNORE, self::ACTION_OVERWRITE];

        return in_array($action, $allowed, true)
            ? $action
            : (in_array($globalAction, $allowed, true) ? $globalAction : self::ACTION_IGNORE);
    }

    private function markTabResolved(RefetchRun $run, RefetchCategory $category): void
    {
        $run->forceFill([
            'resolved_tabs' => array_values(array_unique([
                ...($run->resolved_tabs ?? []),
                $category->value,
            ])),
        ])->save();
    }

    private function allTabsResolved(RefetchRun $run): bool
    {
        $run->refresh();

        return array_diff(RefetchCategory::values(), $run->resolved_tabs ?? []) === [];
    }

    /**
     * @param  EloquentCollection<int, RefetchWorkResult>|null  $results
     */
    private function finalizeAppliedRun(RefetchRun $run, ?EloquentCollection $results = null): void
    {
        ($results ?? $this->fetchedResults($run))
            ->each(function (RefetchWorkResult $result) use ($run): void {
                if (! $result->hasAppliedChanges()) {
                    return;
                }

                $jsonPath = $this->stagedWorkPath($run, $result->product_id) . '.json';

                if (! Storage::disk('local')->exists($jsonPath)) {
                    throw new RuntimeException('refetch.promotion_failed');
                }

                $this->copyFile(
                    'local',
                    $jsonPath,
                    "Works/{$result->product_id}.json",
                );

                // Promote the original response alongside the translated work when changes are applied.
                $raw = Storage::disk('local')->json($jsonPath);
                $originalId = is_array($raw) ? DLSiteWorkData::originalWorkId($raw) : null;
                $hasValidOriginalId = $originalId !== $result->product_id && Validator::make(
                    ['original_id' => $originalId],
                    ['original_id' => ['required', 'string', 'max:191', 'regex:/\A(?:RJ|BJ|VJ)\d+\z/']],
                )->passes();

                if ($hasValidOriginalId) {
                    $filename = "{$result->product_id} - {$originalId}.json";
                    $originalPath = dirname($jsonPath) . '/' . $filename;
                    $destination = "Works/{$filename}";

                    // This supplementary response must not block metadata that has already been applied.
                    try {
                        if (Storage::disk('local')->exists($originalPath)) {
                            $this->copyFile('local', $originalPath, $destination);
                        }
                    } catch (Exception $exception) {
                        Log::warning('Unable to promote original-work JSON after Refetch.', [
                            'run_id' => $run->getKey(),
                            'product_id' => $result->product_id,
                            'source' => $originalPath,
                            'destination' => $destination,
                            'reason' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $run->forceFill([
            'status' => RefetchRun::STATUS_APPLIED,
            'applied_at' => now(),
        ])->save();
    }

    /**
     * @param  list<string>  $resolvedTabs
     * @param  EloquentCollection<int, RefetchWorkResult>|null  $results
     */
    private function finalizeOrRestoreResolvedTabs(
        RefetchRun $run,
        array $resolvedTabs,
        ?EloquentCollection $results = null,
    ): void {
        try {
            $this->finalizeAppliedRun($run, $results);
        } catch (Throwable $exception) {
            $run->forceFill(['resolved_tabs' => $resolvedTabs])->save();

            throw $exception;
        }
    }

    /**
     * @return EloquentCollection<int, RefetchWorkResult>
     */
    private function fetchedResults(RefetchRun $run): EloquentCollection
    {
        return $run->results()
            ->where('status', RefetchWorkResult::STATUS_FETCHED)
            ->with('product')
            ->get();
    }

    private function stagedWorkPath(RefetchRun $run, string $productId): string
    {
        return "Refetch/{$run->getKey()}/Works/{$productId}";
    }

    private function copyFile(string $disk, string $source, string $destination): void
    {
        if (! Storage::disk($disk)->copy($source, $destination)) {
            throw new RuntimeException('refetch.promotion_failed');
        }
    }

    private function withLifecycleLock(Closure $callback): mixed
    {
        try {
            return $this->mutationLock->run(function () use ($callback): mixed {
                $this->imagePromotion->recover();

                return $callback();
            });
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException(
                'refetch.apply.busy',
                previous: $exception,
            );
        }
    }

    private function ensureApplicable(RefetchRun $run): void
    {
        $run->refresh();

        if (! $run->canBeApplied()) {
            throw new RuntimeException($run->applyUnavailableErrorCode());
        }
    }

    private function cleanErrorMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return $message === '' ? 'dlsite.fetch.failed' : mb_strimwidth($message, 0, 1000);
    }

    /**
     * @param  list<string>  $source
     * @param  list<string>  ...$without
     * @return list<string>
     */
    private function tagDiff(array $source, array ...$without): array
    {
        $withoutKeys = array_flip(
            collect(array_merge(...$without))
                ->map(fn(string $tag): string => Genre::titleKey($tag))
                ->all()
        );

        return collect($source)
            ->reject(fn(string $tag): bool => isset($withoutKeys[Genre::titleKey($tag)]))
            ->values()
            ->all();
    }
}
