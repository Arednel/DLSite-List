<?php

namespace App\Support\Autocomplete;

use App\Enums\AutocompleteOrder;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TagAutocompleteSearch
{
    private const RESULT_LIMIT = 20;

    public function __construct(private readonly AutocompleteMatcher $matcher) {}

    /**
     * @return Collection<int, array{value: string, label: string, count: int, type: string}>
     */
    public function search(string $query, AutocompleteOrder $order): Collection
    {
        return Genre::query()
            ->select(['id', 'title'])
            ->withCount('products')
            ->where(fn(Builder $builder) => $this->matcher->whereWordPrefixMatch($builder, 'genres.title', $query))
            ->get()
            ->map(fn(Genre $genre): array => [
                'value' => $genre->title,
                'label' => $genre->title,
                'count' => (int) $genre->products_count,
                'type' => 'tag',
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
