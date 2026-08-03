<?php

namespace App\Support\Refetch;

use App\Enums\ProductContributorRole;
use App\Enums\RefetchCategory;
use App\Models\Genre;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\DLSite\DLSiteWorkFetcher;
use App\Support\ProductContributorSync;
use App\Support\ProductGenreSync;
use App\Support\ProductImageCleanupService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class RefetchService
{
    public const ACTION_INHERIT = 'inherit';

    public const ACTION_IGNORE = 'ignore';

    public const ACTION_OVERWRITE = 'overwrite';

    public const ACTION_DETAILED = 'detailed';

    public const TAG_STALE_MOVE_TO_CUSTOM = 'move_to_custom';

    public const TAG_STALE_REMOVE = 'remove';

    public const TAG_ADDED_ADD = 'add_as_fetched';

    public const TAG_ADDED_IGNORE = 'ignore';

    public const TAG_CUSTOM_PROMOTE = 'promote_to_fetched';

    public const TAG_CUSTOM_KEEP = 'keep_custom';

    public const CANCELLED_BEFORE_FETCH_MESSAGE = 'Refetch was cancelled before this work was fetched.';

    public function __construct(
        private readonly DLSiteWorkFetcher $fetcher,
        private readonly RefetchDiffBuilder $diffBuilder,
        private readonly ProductGenreSync $genreSync,
        private readonly ProductContributorSync $contributorSync,
        private readonly ProductImageCleanupService $imageCleanup,
    ) {}

    /**
     * @param  list<string>  $productIds
     */
    public function createRun(array $productIds, bool $checkImages): RefetchRun
    {
        return Cache::lock(
            RefetchRun::LIFECYCLE_LOCK,
            RefetchRun::LIFECYCLE_LOCK_SECONDS,
        )->block(0, fn(): RefetchRun => DB::transaction(
            function () use ($productIds, $checkImages): RefetchRun {
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
                        ->all()
                );

                return $run;
            }
        ));
    }

    public function fetchAndRecordResult(RefetchWorkResult $result): void
    {
        $product = Product::query()->find($result->product_id);

        if (! $product) {
            $this->recordFailedResult($result, 'Product no longer exists.');

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
            );

            $warnings = [];

            if (in_array('cover.jpg', $fetch->failedImages, true)) {
                $warnings[] = [
                    'key' => 'Cover image download failed after five attempts.',
                    'replace' => [],
                ];
            }

            $failedSamples = collect($fetch->failedImages)
                ->filter(fn(string $filename): bool => str_starts_with($filename, 'sample_'))
                ->values()
                ->all();

            if ($failedSamples !== []) {
                $warnings[] = [
                    'key' => 'Sample image download failed after five attempts: :images',
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
        $result->forceFill([
            'status' => RefetchWorkResult::STATUS_FAILED,
            'error' => $error,
        ])->save();

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
                throw new RuntimeException('This refetch tab was already resolved.');
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

            $decisions = $result->decisions ?? [];

            foreach ($changes as $field => $change) {
                $action = $this->resolvedAction(
                    data_get($actions, "{$result->getKey()}.{$field}"),
                    $globalAction,
                    $category === RefetchCategory::Tags,
                );
                $changed = false;

                if ($action === self::ACTION_OVERWRITE) {
                    $changed = $this->applyChange($result, $category, $field, $change);
                } elseif ($action === self::ACTION_DETAILED && $category === RefetchCategory::Tags) {
                    $changed = $this->applyDetailedTags(
                        $result,
                        $change,
                        $tagActions[(string) $result->getKey()] ?? [],
                    );
                }

                $decisions[$category->value][$field] = [
                    'action' => $action,
                    'changed' => $changed,
                    'tag_actions' => $action === self::ACTION_DETAILED
                        ? ($tagActions[(string) $result->getKey()] ?? [])
                        : null,
                ];
            }

            $result->forceFill(['decisions' => $decisions])->save();
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

            return $this->promoteProductImages(
                $result,
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

            return $this->promoteProductImages(
                $result,
                $product,
                $promotions,
                ['sample_images' => $paths],
            );
        }

        return DB::transaction(function () use ($product, $category, $field, $change): bool {
            if (in_array($category, [
                RefetchCategory::Titles,
                RefetchCategory::Descriptions,
                RefetchCategory::Series,
                RefetchCategory::Age,
            ], true)) {
                $product->forceFill([$field => $change['new']]);

                if (! $product->isDirty($field)) {
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

            if ($category === RefetchCategory::Tags) {
                return $this->overwriteFetchedTags($product, $change);
            }

            return false;
        });
    }

    /**
     * @param  list<array{source: string, destination: string}>  $promotions
     * @param  array<string, mixed>  $attributes
     */
    private function promoteProductImages(
        RefetchWorkResult $result,
        Product $product,
        array $promotions,
        array $attributes,
    ): bool {
        $disk = Storage::disk('public');
        $backupDirectory = "Refetch/{$result->refetch_run_id}/Backups/Works/{$product->getKey()}";
        $backups = [];

        try {
            foreach ($promotions as $promotion) {
                $destination = $promotion['destination'];
                $backup = null;

                if ($disk->exists($destination)) {
                    $backup = "{$backupDirectory}/" . basename($destination);
                    $this->copyFile('public', $destination, $backup);
                }

                $backups[$destination] = $backup;
            }

            foreach ($promotions as $promotion) {
                $this->copyFile(
                    'public',
                    $promotion['source'],
                    $promotion['destination'],
                );
            }

            DB::transaction(function () use ($attributes, $product): void {
                $product->forceFill($attributes)->touch();
            });
        } catch (Throwable $exception) {
            $this->restoreProductImages($disk, $backups, $backupDirectory);

            throw $exception;
        }

        $this->imageCleanup->cleanup($product);
        $disk->deleteDirectory($backupDirectory);

        return true;
    }

    /**
     * @param  array<string, string|null>  $backups
     */
    private function restoreProductImages(
        FilesystemAdapter $disk,
        array $backups,
        string $backupDirectory,
    ): void {
        try {
            foreach ($backups as $destination => $backup) {
                if ($backup === null) {
                    if ($disk->exists($destination) && ! $disk->delete($destination)) {
                        throw new RuntimeException('Failed to remove promoted refetch image.');
                    }

                    continue;
                }

                $this->copyFile('public', $backup, $destination);
            }

            $disk->deleteDirectory($backupDirectory);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Failed to restore work images after refetch promotion failed.',
                previous: $exception,
            );
        }
    }

    private function overwriteFetchedTags(Product $product, array $change): bool
    {
        $custom = $product->customGenres()->pluck('genres.title')->all();
        $japanese = $this->tagDiff($change['new']['japanese'] ?? [], $custom);
        $english = $this->tagDiff($change['new']['english'] ?? [], $custom);

        return $this->genreSync->sync($product, [
            Genre::LANGUAGE_JAPANESE => Genre::resolveIdsFromTitles($japanese),
            Genre::LANGUAGE_ENGLISH => Genre::resolveIdsFromTitles($english),
        ], Genre::resolveIdsFromTitles($custom));
    }

    private function applyDetailedTags(
        RefetchWorkResult $result,
        array $change,
        array $actions,
    ): bool {
        $product = $result->product;

        if (! $product) {
            return false;
        }

        $details = $change['details'] ?? [];
        $fetchedJapanese = $change['new']['japanese'] ?? [];
        $fetchedEnglish = $change['new']['english'] ?? [];
        $custom = $change['old']['custom'] ?? [];
        $customToFetched = array_merge(
            $details['custom_to_fetched_japanese'] ?? [],
            $details['custom_to_fetched_english'] ?? [],
        );

        if (($actions['added_japanese'] ?? self::TAG_ADDED_ADD) === self::TAG_ADDED_IGNORE) {
            $fetchedJapanese = $this->tagDiff($fetchedJapanese, $details['added_japanese'] ?? []);
        }

        if (($actions['added_english'] ?? self::TAG_ADDED_ADD) === self::TAG_ADDED_IGNORE) {
            $fetchedEnglish = $this->tagDiff($fetchedEnglish, $details['added_english'] ?? []);
        }

        if (($actions['custom_to_fetched'] ?? self::TAG_CUSTOM_PROMOTE) === self::TAG_CUSTOM_KEEP) {
            $fetchedJapanese = $this->tagDiff($fetchedJapanese, $customToFetched);
            $fetchedEnglish = $this->tagDiff($fetchedEnglish, $customToFetched);
        } else {
            $custom = $this->tagDiff($custom, $customToFetched);
        }

        if (($actions['stale_japanese'] ?? self::TAG_STALE_MOVE_TO_CUSTOM) === self::TAG_STALE_MOVE_TO_CUSTOM) {
            $custom = array_merge(
                $custom,
                $this->tagDiff($details['stale_japanese'] ?? [], $fetchedJapanese, $fetchedEnglish),
            );
        }

        if (($actions['stale_english'] ?? self::TAG_STALE_MOVE_TO_CUSTOM) === self::TAG_STALE_MOVE_TO_CUSTOM) {
            $custom = array_merge(
                $custom,
                $this->tagDiff($details['stale_english'] ?? [], $fetchedJapanese, $fetchedEnglish),
            );
        }

        return $this->genreSync->sync($product, [
            Genre::LANGUAGE_JAPANESE => Genre::resolveIdsFromTitles($fetchedJapanese),
            Genre::LANGUAGE_ENGLISH => Genre::resolveIdsFromTitles($fetchedEnglish),
        ], Genre::resolveIdsFromTitles($custom));
    }

    private function resolvedAction(?string $action, string $globalAction, bool $allowDetailed): string
    {
        $allowed = [
            self::ACTION_IGNORE,
            self::ACTION_OVERWRITE,
            ...($allowDetailed ? [self::ACTION_DETAILED] : []),
        ];

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
                    throw new RuntimeException('Failed to promote staged refetch file.');
                }

                $this->copyFile(
                    'local',
                    $jsonPath,
                    "Works/{$result->product_id}.json",
                );
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
            throw new RuntimeException('Failed to promote staged refetch file.');
        }
    }

    private function withLifecycleLock(Closure $callback): mixed
    {
        try {
            return Cache::lock(
                RefetchRun::LIFECYCLE_LOCK,
                RefetchRun::LIFECYCLE_LOCK_SECONDS,
            )->block(0, $callback);
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException(
                'Refetch changes cannot be applied while another refetch action is in progress.',
                previous: $exception,
            );
        }
    }

    private function ensureApplicable(RefetchRun $run): void
    {
        $run->refresh();

        if (! $run->canBeApplied()) {
            throw new RuntimeException($run->applyUnavailableMessage());
        }
    }

    private function cleanErrorMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return $message === '' ? 'DLSite work fetch failed.' : mb_strimwidth($message, 0, 1000);
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
