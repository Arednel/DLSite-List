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
        $useFirstWordRank = $order === AutocompleteOrder::FirstWord;

        return Product::query()
            ->select('series')
            ->selectRaw('COUNT(*) as series_count')
            ->whereNotNull('series')
            ->where('series', '<>', '')
            ->where(fn(Builder $builder) => $this->matcher->whereWordPrefixMatch($builder, 'series', $query))
            ->groupBy('series')
            ->when($useFirstWordRank, fn(Builder $builder) => $this->matcher->orderByMatchRank($builder, 'series', $query))
            ->orderByDesc('series_count')
            ->orderByRaw('LOWER(series)')
            ->limit(self::RESULT_LIMIT)
            ->toBase()
            ->get()
            ->map(fn(object $row): array => [
                'value' => (string) $row->series,
                'label' => (string) $row->series,
                'count' => (int) $row->series_count,
                'type' => 'series',
            ])
            ->sort(fn(array $left, array $right): int => $this->matcher->compareSuggestions(
                $left,
                $right,
                $query,
                $useFirstWordRank,
            ))
            ->values();
    }
}
