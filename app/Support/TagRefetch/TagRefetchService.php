<?php

namespace App\Support\TagRefetch;

use App\Models\Genre;
use App\Models\Product;
use App\Models\TagRefetchRun;
use App\Models\TagRefetchWorkResult;
use Illuminate\Support\Facades\DB;
use Throwable;

class TagRefetchService
{
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
                    ->map(fn (string $productId): array => [
                        'product_id' => $productId,
                        'status' => TagRefetchWorkResult::STATUS_PENDING,
                        'fetched_japanese_tags' => [],
                        'fetched_english_tags' => [],
                        'added_japanese_tags' => [],
                        'added_english_tags' => [],
                        'stale_japanese_tags' => [],
                        'stale_english_tags' => [],
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

        if ($run->status === TagRefetchRun::STATUS_RUNNING && $pending === 0) {
            $updates['status'] = TagRefetchRun::STATUS_REVIEW;
            $updates['completed_at'] = now();
        }

        $run->forceFill($updates)->save();
    }

    public function applyRun(
        TagRefetchRun $run,
        string $globalJapaneseAction = TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
        string $globalEnglishAction = TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
        array $workActions = [],
    ): void {
        $run->results()
            ->where('status', TagRefetchWorkResult::STATUS_FETCHED)
            ->with('product')
            ->get()
            ->each(function (TagRefetchWorkResult $result) use ($globalJapaneseAction, $globalEnglishAction, $workActions): void {
                $japaneseAction = $this->resolveWorkAction(
                    data_get($workActions, "{$result->product_id}.japanese"),
                    $globalJapaneseAction
                );
                $englishAction = $this->resolveWorkAction(
                    data_get($workActions, "{$result->product_id}.english"),
                    $globalEnglishAction
                );

                DB::transaction(fn () => $this->applyResult($result, $japaneseAction, $englishAction));
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
     *     stale_english_tags: list<string>
     * }
     */
    public function diffProductTags(Product $product, array $fetchedJapanese, array $fetchedEnglish): array
    {
        $fetchedJapanese = $this->normalizeTags($fetchedJapanese);
        $fetchedEnglish = $this->normalizeTags($fetchedEnglish);

        $currentJapanese = $this->currentFetchedTitles($product, Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $currentEnglish = $this->currentFetchedTitles($product, Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $customTitles = $this->currentCustomTitles($product);

        return [
            'fetched_japanese_tags' => $fetchedJapanese,
            'fetched_english_tags' => $fetchedEnglish,
            'added_japanese_tags' => $this->titleDiff($fetchedJapanese, $currentJapanese, $customTitles),
            'added_english_tags' => $this->titleDiff($fetchedEnglish, $currentEnglish, $customTitles),
            'stale_japanese_tags' => $this->titleDiff($currentJapanese, $fetchedJapanese),
            'stale_english_tags' => $this->titleDiff($currentEnglish, $fetchedEnglish),
        ];
    }

    private function applyResult(TagRefetchWorkResult $result, string $japaneseAction, string $englishAction): void
    {
        $product = $result->product;

        if (! $product) {
            return;
        }

        $customTitles = $this->currentCustomTitles($product);
        $fetchedJapanese = $this->titleDiff($result->fetched_japanese_tags ?? [], $customTitles);
        $fetchedEnglish = $this->titleDiff($result->fetched_english_tags ?? [], $customTitles);

        $fetchedGenreIds = array_merge(
            Genre::resolveIdsFromTitles($fetchedJapanese, Genre::TYPE_AUTO_GENERATED_JAPANESE, Genre::LANGUAGE_JAPANESE),
            Genre::resolveIdsFromTitles($fetchedEnglish, Genre::TYPE_AUTO_GENERATED_ENGLISH, Genre::LANGUAGE_ENGLISH),
        );

        $customGenreIds = $this->currentCustomGenreIds($product);

        if ($japaneseAction === TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM) {
            $customGenreIds = array_merge(
                $customGenreIds,
                Genre::resolveIdsFromTitles($result->stale_japanese_tags ?? [], Genre::TYPE_CUSTOM, Genre::LANGUAGE_ENGLISH)
            );
        }

        if ($englishAction === TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM) {
            $customGenreIds = array_merge(
                $customGenreIds,
                Genre::resolveIdsFromTitles($result->stale_english_tags ?? [], Genre::TYPE_CUSTOM, Genre::LANGUAGE_ENGLISH)
            );
        }

        $product->genres()->sync($this->syncPayload($fetchedGenreIds, $customGenreIds));

        $result->forceFill([
            'stale_japanese_action' => $japaneseAction,
            'stale_english_action' => $englishAction,
        ])->save();
    }

    private function resolveWorkAction(?string $workAction, string $globalAction): string
    {
        return in_array($workAction, [
            TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            TagRefetchWorkResult::STALE_ACTION_REMOVE,
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
    private function currentFetchedTitles(Product $product, string $type): array
    {
        return DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->where('genre_product.product_id', $product->getKey())
            ->where('genre_product.source', Genre::PIVOT_SOURCE_FETCHED)
            ->where('genres.type', $type)
            ->orderBy('genres.title')
            ->pluck('genres.title')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function currentCustomTitles(Product $product): array
    {
        return DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->where('genre_product.product_id', $product->getKey())
            ->where('genre_product.source', Genre::PIVOT_SOURCE_CUSTOM)
            ->pluck('genres.title')
            ->all();
    }

    /**
     * @return list<int>
     */
    private function currentCustomGenreIds(Product $product): array
    {
        return DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('source', Genre::PIVOT_SOURCE_CUSTOM)
            ->pluck('genre_id')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        return collect($tags)
            ->map(fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->unique()
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

        return collect($source)
            ->reject(fn (string $title): bool => in_array($title, $without, true))
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $fetchedGenreIds
     * @param  list<int>  $customGenreIds
     */
    private function syncPayload(array $fetchedGenreIds, array $customGenreIds): array
    {
        $payload = [];

        foreach (array_unique($fetchedGenreIds) as $genreId) {
            $payload[$genreId] = ['source' => Genre::PIVOT_SOURCE_FETCHED];
        }

        foreach (array_unique($customGenreIds) as $genreId) {
            $payload[$genreId] = ['source' => Genre::PIVOT_SOURCE_CUSTOM];
        }

        return $payload;
    }
}
