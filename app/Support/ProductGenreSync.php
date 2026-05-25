<?php

namespace App\Support;

use App\Models\Genre;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class ProductGenreSync
{
    /**
     * @param  array<string, list<int|string>>  $fetchedGenreIdsByLanguage
     * @param  list<int|string>  $customGenreIds
     */
    public function sync(Product $product, array $fetchedGenreIdsByLanguage, array $customGenreIds): void
    {
        $languageMap = GenreSyncPayload::languageMap($fetchedGenreIdsByLanguage);

        DB::transaction(function () use ($product, $customGenreIds, $languageMap): void {
            $product->genres()->sync(
                GenreSyncPayload::build(array_keys($languageMap), $customGenreIds)
            );

            $this->syncLanguageRows($product, $languageMap);
        });
    }

    /**
     * @param  list<int|string>  $customGenreIds
     */
    public function syncCustom(Product $product, array $customGenreIds): bool
    {
        $fetchedGenreIdsByLanguage = $this->currentFetchedGenreIdsByLanguage($product);
        $fetchedGenreIds = collect($fetchedGenreIdsByLanguage)->flatten()->unique()->values();
        $effectiveCustomGenreIds = collect($customGenreIds)
            ->map(fn(int|string $genreId): int => (int) $genreId)
            ->diff($fetchedGenreIds->map(fn(int|string $genreId): int => (int) $genreId))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $currentCustomGenreIds = $this->currentCustomGenreIds($product);

        $this->sync($product, $fetchedGenreIdsByLanguage, $effectiveCustomGenreIds);

        return $currentCustomGenreIds !== $effectiveCustomGenreIds;
    }

    /**
     * @return array<string, list<int>>
     */
    public function currentFetchedGenreIdsByLanguage(Product $product): array
    {
        return DB::table('genre_product')
            ->join('genre_product_languages', 'genre_product_languages.genre_product_id', '=', 'genre_product.id')
            ->where('genre_product.product_id', $product->getKey())
            ->where('genre_product.source', Genre::PIVOT_SOURCE_FETCHED)
            ->orderBy('genre_product_languages.language')
            ->orderBy('genre_product.genre_id')
            ->get([
                'genre_product.genre_id',
                'genre_product_languages.language',
            ])
            ->groupBy('language')
            ->map(fn($rows) => $rows
                ->pluck('genre_id')
                ->map(fn(int|string $genreId): int => (int) $genreId)
                ->unique()
                ->values()
                ->all())
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
            ->map(fn(int|string $genreId): int => (int) $genreId)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string, list<string>>  $languageMap
     */
    private function syncLanguageRows(Product $product, array $languageMap): void
    {
        $pivotIdsByGenreId = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->pluck('id', 'genre_id');

        if ($pivotIdsByGenreId->isNotEmpty()) {
            DB::table('genre_product_languages')
                ->whereIn('genre_product_id', $pivotIdsByGenreId->values()->all())
                ->delete();
        }

        $now = now();
        $rows = collect($languageMap)
            ->flatMap(function (array $languages, int|string $genreId) use ($pivotIdsByGenreId, $now) {
                $pivotId = $pivotIdsByGenreId[(string) $genreId] ?? null;

                if ($pivotId === null) {
                    return [];
                }

                return collect($languages)
                    ->map(fn(string $language): array => [
                        'genre_product_id' => $pivotId,
                        'language' => $language,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();
            })
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('genre_product_languages')->insertOrIgnore($rows);
        }
    }
}
