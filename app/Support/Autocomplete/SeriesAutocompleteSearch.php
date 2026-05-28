<?php

namespace App\Support\Autocomplete;

use App\Enums\AutocompleteOrder;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SeriesAutocompleteSearch
{
    private const RESULT_LIMIT = 20;

    public function __construct(private readonly AutocompleteMatcher $matcher) {}

    /**
     * @return Collection<int, array{value: string, label: string, count: int, type: string}>
     */
    public function search(string $query, AutocompleteOrder $order): Collection
    {
        return Product::query()
            ->select(['series'])
            ->whereNotNull('series')
            ->where('series', '<>', '')
            ->where(fn(Builder $builder) => $this->matcher->whereWordPrefixMatch($builder, 'series', $query))
            ->get()
            ->countBy('series')
            ->map(fn(int $count, string $series): array => [
                'value' => $series,
                'label' => $series,
                'count' => $count,
                'type' => 'series',
            ])
            ->sort(fn(array $left, array $right): int => $this->matcher->compareSuggestions(
                $left,
                $right,
                $query,
                $order === AutocompleteOrder::FirstWord,
            ))
            ->take(self::RESULT_LIMIT)
            ->values();
    }
}
