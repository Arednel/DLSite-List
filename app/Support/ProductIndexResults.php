<?php

namespace App\Support;

use App\Enums\ProductIndexSortField;
use App\Models\Genre;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

final class ProductIndexResults
{
    public function getProducts(ProductIndexFilters $filters): EloquentCollection
    {
        $products = Product::query()
            ->when(
                $filters->ageCategory !== null,
                fn($query) => $query->where('age_category', $filters->ageCategory->value)
            )
            ->when(
                $filters->progress !== null,
                fn($query) => $query->where('progress', $filters->progress->value)
            )
            ->when(
                $filters->genre !== '',
                fn($query) => $query->filterGenre($filters->genre)
            )
            ->when(
                $filters->series !== '',
                fn($query) => $query->where('series', $filters->series)
            )
            ->when(
                $filters->title !== '',
                fn($query) => $query->filterTitle($filters->title)
            )
            ->when(
                $filters->notes !== '',
                fn($query) => $query->filterNotes($filters->notes)
            )
            ->when(
                $filters->score !== null,
                fn($query) => $query->where('score', (int) $filters->score->value)
            )
            ->when(
                $filters->priority !== null,
                fn($query) => $query->where('priority', (int) $filters->priority->value)
            )
            ->when(
                $filters->numReListenTimes !== null,
                fn($query) => $query->where('num_re_listen_times', $filters->numReListenTimes)
            )
            ->when(
                $filters->reListenValue !== null,
                fn($query) => $query->where('re_listen_value', (int) $filters->reListenValue->value)
            )
            ->when(
                $filters->tags !== '',
                fn($query) => $query->filterTags(
                    $filters->parsedTags(),
                    $filters->resolvedTagMatch(),
                )
            )
            ->when(
                $filters->search !== '',
                fn($query) => $query->searchIndex($filters->search)
            )
            ->get();

        return $this->sortProducts($products, $filters->sorts());
    }

    public function loadVisibleGenres(array $productIds): \Illuminate\Support\Collection
    {
        if ($productIds === []) {
            return collect();
        }

        // Index only needs visible EN/custom tags, so use one lightweight query
        // instead of hydrating genre relationships for every listed product.
        return DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->whereIn('genre_product.product_id', $productIds)
            ->where(function ($query): void {
                $query->where('genre_product.source', Genre::PIVOT_SOURCE_CUSTOM)
                    ->orWhere('genres.type', Genre::TYPE_AUTO_GENERATED_ENGLISH)
                    ->orWhere('genres.type', Genre::TYPE_CUSTOM);
            })
            ->orderBy('genres.title')
            ->get([
                'genre_product.product_id',
                'genres.id',
                'genres.title',
            ])
            ->groupBy('product_id');
    }

    /**
     * @param  list<ProductIndexSort>  $sorts
     */
    private function sortProducts(EloquentCollection $products, array $sorts): EloquentCollection
    {
        $items = $products->all();

        usort($items, function (Product $left, Product $right) use ($sorts): int {
            foreach ($sorts as $sort) {
                $comparison = $this->compareSortableValues(
                    $this->productSortValue($left, $sort->field),
                    $this->productSortValue($right, $sort->field),
                    $sort->direction->value,
                );

                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return $this->rjSortValue($right) <=> $this->rjSortValue($left);
        });

        return new EloquentCollection(array_values($items));
    }

    private function compareSortableValues(mixed $left, mixed $right, string $direction): int
    {
        if ($left === $right) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        $comparison = $left <=> $right;

        return $direction === 'desc' ? -$comparison : $comparison;
    }

    private function productSortValue(Product $product, ProductIndexSortField $field): int|string|null
    {
        return match ($field) {
            ProductIndexSortField::Score => $product->score,
            ProductIndexSortField::Priority => $product->priority,
            ProductIndexSortField::TotalTimesReListened => $product->num_re_listen_times,
            ProductIndexSortField::ReListenValue => $product->re_listen_value,
            ProductIndexSortField::StartDate => $this->dateSortValue($product->start_date),
            ProductIndexSortField::FinishDate => $this->dateSortValue($product->end_date),
        };
    }

    private function dateSortValue(?array $date): ?string
    {
        if (!is_array($date)) {
            return null;
        }

        $year = $this->dateSortPart($date['year'] ?? null);
        $month = $this->dateSortPart($date['month'] ?? null);
        $day = $this->dateSortPart($date['day'] ?? null);

        if ($year === null && $month === null && $day === null) {
            return null;
        }

        return sprintf('%04d%02d%02d', $year ?? 0, $month ?? 0, $day ?? 0);
    }

    private function dateSortPart(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function rjSortValue(Product $product): int
    {
        return (int) substr($product->id, 2);
    }
}
