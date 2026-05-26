<?php

namespace App\Support\TagRefetch;

use App\Models\Genre;
use App\Models\Product;
use App\Models\TagRefetchRun;
use App\Models\TagRefetchWorkResult;
use App\Support\ProductGenreSync;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

class TagRefetchService
{
    public const CANCELLED_BEFORE_FETCH_MESSAGE = 'Refetch was cancelled before this work was fetched.';

    public function __construct(
        private readonly ProductGenreSync $genreSync,
    ) {}

    /**
     * @param  list<string>  $productIds
     */
    public function createRun(array $productIds): TagRefetchRun
    {
        return DB::transaction(function () use ($productIds): TagRefetchRun {
            $run = TagRefetchRun::query()->create([
                'status' => TagRefetchRun::STATUS_RUNNING,
                'selected_product_ids' => array_values($productIds),
                'total_count' => count($productIds),
                'processed_count' => 0,
                'fetched_count' => 0,
                'skipped_count' => 0,
                'started_at' => now(),
            ]);

            $run->results()->createMany(
                collect($productIds)
                    ->map(fn(string $productId): array => [
                        'product_id' => $productId,
                        'status' => TagRefetchWorkResult::STATUS_PENDING,
                        'fetched_japanese_tags' => [],
                        'fetched_english_tags' => [],
                        'added_japanese_tags' => [],
                        'added_english_tags' => [],
                        'stale_japanese_tags' => [],
                        'stale_english_tags' => [],
                        'custom_to_fetched_japanese_tags' => [],
                        'custom_to_fetched_english_tags' => [],
                    ])
                    ->all()
            );

            return $run;
        });
    }

    /**
     * @param  array{japanese: list<string>, english: list<string>}  $fetchedTags
     */
    public function recordFetchedResult(TagRefetchWorkResult $result, array $fetchedTags, ?Product $product = null): void
    {
        $product ??= Product::query()->find($result->product_id);

        if (! $product) {
            $this->recordSkippedResult($result, 'Product no longer exists.');

            return;
        }

        $diff = $this->diffProductTags(
            $product,
            $fetchedTags['japanese'] ?? [],
            $fetchedTags['english'] ?? []
        );

        $result->forceFill([
            'status' => TagRefetchWorkResult::STATUS_FETCHED,
            'fetched_japanese_tags' => $diff['fetched_japanese_tags'],
            'fetched_english_tags' => $diff['fetched_english_tags'],
            'added_japanese_tags' => $diff['added_japanese_tags'],
            'added_english_tags' => $diff['added_english_tags'],
            'stale_japanese_tags' => $diff['stale_japanese_tags'],
            'stale_english_tags' => $diff['stale_english_tags'],
            'custom_to_fetched_japanese_tags' => $diff['custom_to_fetched_japanese_tags'],
            'custom_to_fetched_english_tags' => $diff['custom_to_fetched_english_tags'],
            'error' => null,
        ])->save();

        $this->refreshRunProgress($result->run()->firstOrFail());
    }

    public function fetchAndRecordResult(TagRefetchWorkResult $result, DLSiteTagFetcher $fetcher): void
    {
        $product = Product::query()->find($result->product_id);

        if (! $product) {
            $this->recordSkippedResult($result, 'Product no longer exists.');

            return;
        }

        if ($product->maker_id === null) {
            $this->recordSkippedResult($result, 'Custom-only work is skipped.');

            return;
        }

        try {
            $this->recordFetchedResult($result, $fetcher->fetch($result->product_id), $product);
        } catch (Throwable $exception) {
            $this->recordSkippedResult($result, $this->cleanErrorMessage($exception));
        }
    }

    public function recordSkippedResult(TagRefetchWorkResult $result, string $error): void
    {
        $result->forceFill([
            'status' => TagRefetchWorkResult::STATUS_SKIPPED,
            'error' => $error,
        ])->save();

        $this->refreshRunProgress($result->run()->firstOrFail());
    }

    public function refreshRunProgress(TagRefetchRun $run): void
    {
        $counts = $run->results()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pending = (int) ($counts[TagRefetchWorkResult::STATUS_PENDING] ?? 0);
        $fetched = (int) ($counts[TagRefetchWorkResult::STATUS_FETCHED] ?? 0);
        $skipped = (int) ($counts[TagRefetchWorkResult::STATUS_SKIPPED] ?? 0);
        $processed = $fetched + $skipped;

        $updates = [
            'processed_count' => $processed,
            'fetched_count' => $fetched,
            'skipped_count' => $skipped,
        ];

        if ($run->isActive() && $pending === 0) {
            $updates['status'] = TagRefetchRun::STATUS_REVIEW;
            $updates['completed_at'] = now();
        }

        $run->forceFill($updates)->save();
    }

    public function cancelRun(TagRefetchRun $run): bool
    {
        $run->refresh();

        if ($run->isCancelling()) {
            return true;
        }

        if (! $run->canBeCancelled()) {
            return false;
        }

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_CANCELLING,
            'cancelled_at' => $run->cancelled_at ?? now(),
        ])->save();

        if ($run->batch_id !== null) {
            Bus::findBatch($run->batch_id)?->cancel();
        }

        $this->refreshRunProgress($run);

        return true;
    }

    public function applyRun(
        TagRefetchRun $run,
        string $globalJapaneseAction = TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
        string $globalEnglishAction = TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
        string $globalAddedJapaneseAction = TagRefetchWorkResult::ADDED_ACTION_ADD,
        string $globalAddedEnglishAction = TagRefetchWorkResult::ADDED_ACTION_ADD,
        string $globalCustomToFetchedAction = TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_PROMOTE,
        array $workActions = [],
    ): void {
        $run->results()
            ->where('status', TagRefetchWorkResult::STATUS_FETCHED)
            ->with('product')
            ->get()
            ->each(function (TagRefetchWorkResult $result) use (
                $globalJapaneseAction,
                $globalEnglishAction,
                $globalAddedJapaneseAction,
                $globalAddedEnglishAction,
                $globalCustomToFetchedAction,
                $workActions,
            ): void {
                $japaneseAction = $this->resolveWorkAction(
                    data_get($workActions, "{$result->product_id}.japanese"),
                    $globalJapaneseAction
                );
                $englishAction = $this->resolveWorkAction(
                    data_get($workActions, "{$result->product_id}.english"),
                    $globalEnglishAction
                );
                $addedJapaneseAction = $this->resolveAddedAction(
                    data_get($workActions, "{$result->product_id}.added_japanese"),
                    $globalAddedJapaneseAction
                );
                $addedEnglishAction = $this->resolveAddedAction(
                    data_get($workActions, "{$result->product_id}.added_english"),
                    $globalAddedEnglishAction
                );
                $customToFetchedAction = $this->resolveCustomToFetchedAction(
                    data_get($workActions, "{$result->product_id}.custom_to_fetched"),
                    $globalCustomToFetchedAction
                );

                DB::transaction(fn() => $this->applyResult(
                    $result,
                    $japaneseAction,
                    $englishAction,
                    $addedJapaneseAction,
                    $addedEnglishAction,
                    $customToFetchedAction,
                ));
            });

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_APPLIED,
            'applied_at' => now(),
        ])->save();
    }

    /**
     * @return array{
     *     fetched_japanese_tags: list<string>,
     *     fetched_english_tags: list<string>,
     *     added_japanese_tags: list<string>,
     *     added_english_tags: list<string>,
     *     stale_japanese_tags: list<string>,
     *     stale_english_tags: list<string>,
     *     custom_to_fetched_japanese_tags: list<string>,
     *     custom_to_fetched_english_tags: list<string>
     * }
     */
    public function diffProductTags(Product $product, array $fetchedJapanese, array $fetchedEnglish): array
    {
        $fetchedJapanese = $this->normalizeTags($fetchedJapanese);
        $fetchedEnglish = $this->normalizeTags($fetchedEnglish);

        $currentJapanese = $this->currentFetchedTitles($product, Genre::LANGUAGE_JAPANESE);
        $currentEnglish = $this->currentFetchedTitles($product, Genre::LANGUAGE_ENGLISH);
        $customTitles = $this->currentCustomTitles($product);

        return [
            'fetched_japanese_tags' => $fetchedJapanese,
            'fetched_english_tags' => $fetchedEnglish,
            'added_japanese_tags' => $this->titleDiff($fetchedJapanese, $currentJapanese, $customTitles),
            'added_english_tags' => $this->titleDiff($fetchedEnglish, $currentEnglish, $customTitles),
            'stale_japanese_tags' => $this->titleDiff($currentJapanese, $fetchedJapanese),
            'stale_english_tags' => $this->titleDiff($currentEnglish, $fetchedEnglish),
            'custom_to_fetched_japanese_tags' => $this->titleIntersection($fetchedJapanese, $customTitles),
            'custom_to_fetched_english_tags' => $this->titleIntersection($fetchedEnglish, $customTitles),
        ];
    }

    private function applyResult(
        TagRefetchWorkResult $result,
        string $japaneseAction,
        string $englishAction,
        string $addedJapaneseAction,
        string $addedEnglishAction,
        string $customToFetchedAction,
    ): void {
        $product = $result->product;

        if (! $product) {
            return;
        }

        $customTitles = $this->currentCustomTitles($product);
        $customToFetchedTags = array_merge(
            $result->custom_to_fetched_japanese_tags ?? [],
            $result->custom_to_fetched_english_tags ?? [],
        );

        $fetchedJapanese = $result->fetched_japanese_tags ?? [];
        $fetchedEnglish = $result->fetched_english_tags ?? [];

        if ($addedJapaneseAction === TagRefetchWorkResult::ADDED_ACTION_IGNORE) {
            $fetchedJapanese = $this->titleDiff($fetchedJapanese, $result->added_japanese_tags ?? []);
        }

        if ($addedEnglishAction === TagRefetchWorkResult::ADDED_ACTION_IGNORE) {
            $fetchedEnglish = $this->titleDiff($fetchedEnglish, $result->added_english_tags ?? []);
        }

        if ($customToFetchedAction === TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_KEEP_CUSTOM) {
            $fetchedJapanese = $this->titleDiff($fetchedJapanese, $customToFetchedTags);
            $fetchedEnglish = $this->titleDiff($fetchedEnglish, $customToFetchedTags);
        } else {
            $customTitles = $this->titleDiff($customTitles, $customToFetchedTags);
        }

        if ($japaneseAction === TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM) {
            $customTitles = array_merge(
                $customTitles,
                $this->titleDiff($result->stale_japanese_tags ?? [], $fetchedJapanese, $fetchedEnglish)
            );
        }

        if ($englishAction === TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM) {
            $customTitles = array_merge(
                $customTitles,
                $this->titleDiff($result->stale_english_tags ?? [], $fetchedJapanese, $fetchedEnglish)
            );
        }

        $this->genreSync->sync($product, [
            Genre::LANGUAGE_JAPANESE => Genre::resolveIdsFromTitles($fetchedJapanese),
            Genre::LANGUAGE_ENGLISH => Genre::resolveIdsFromTitles($fetchedEnglish),
        ], Genre::resolveIdsFromTitles($customTitles));

        $result->forceFill([
            'added_japanese_action' => $addedJapaneseAction,
            'added_english_action' => $addedEnglishAction,
            'stale_japanese_action' => $japaneseAction,
            'stale_english_action' => $englishAction,
            'custom_to_fetched_action' => $customToFetchedAction,
        ])->save();
    }

    private function resolveWorkAction(?string $workAction, string $globalAction): string
    {
        return in_array($workAction, [
            TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            TagRefetchWorkResult::STALE_ACTION_REMOVE,
        ], true) ? $workAction : $globalAction;
    }

    private function resolveAddedAction(?string $workAction, string $globalAction): string
    {
        return in_array($workAction, [
            TagRefetchWorkResult::ADDED_ACTION_ADD,
            TagRefetchWorkResult::ADDED_ACTION_IGNORE,
        ], true) ? $workAction : $globalAction;
    }

    private function resolveCustomToFetchedAction(?string $workAction, string $globalAction): string
    {
        return in_array($workAction, [
            TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_PROMOTE,
            TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_KEEP_CUSTOM,
        ], true) ? $workAction : $globalAction;
    }

    private function cleanErrorMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return $message === '' ? 'DLSite tag fetch failed.' : mb_strimwidth($message, 0, 1000);
    }

    /**
     * @return list<string>
     */
    private function currentFetchedTitles(Product $product, string $language): array
    {
        return match ($language) {
            Genre::LANGUAGE_JAPANESE => $product->japaneseGenres()
                ->orderBy('genres.title')
                ->pluck('genres.title')
                ->all(),
            Genre::LANGUAGE_ENGLISH => $product->englishGenres()
                ->orderBy('genres.title')
                ->pluck('genres.title')
                ->all(),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function currentCustomTitles(Product $product): array
    {
        return $product->customGenres()
            ->pluck('genres.title')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        return collect($tags)
            ->map(fn(mixed $tag): string => trim((string) $tag))
            ->filter()
            ->unique(fn(string $tag): string => Genre::titleKey($tag))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $source
     * @param  list<string>  ...$withoutLists
     * @return list<string>
     */
    private function titleDiff(array $source, array ...$withoutLists): array
    {
        $without = array_merge(...$withoutLists);
        $withoutKeys = array_flip(array_map(
            fn(string $title): string => Genre::titleKey($title),
            $without,
        ));

        return collect($source)
            ->reject(fn(string $title): bool => isset($withoutKeys[Genre::titleKey($title)]))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $source
     * @param  list<string>  $only
     * @return list<string>
     */
    private function titleIntersection(array $source, array $only): array
    {
        $onlyKeys = array_flip(array_map(
            fn(string $title): string => Genre::titleKey($title),
            $only,
        ));

        return collect($source)
            ->filter(fn(string $title): bool => isset($onlyKeys[Genre::titleKey($title)]))
            ->values()
            ->all();
    }
}
