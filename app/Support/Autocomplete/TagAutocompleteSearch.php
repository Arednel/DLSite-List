<?php

namespace App\Support\Autocomplete;

use App\Enums\AutocompleteOrder;
use App\Models\Genre;
use App\Support\TagColor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TagAutocompleteSearch
{
    private const RESULT_LIMIT = 20;

    public function __construct(private readonly AutocompleteMatcher $matcher) {}

    /**
     * @return Collection<int, array{value: string, label: string, count: int, type: string, color?: ?string, text_color?: ?string}>
     */
    public function search(string $query, AutocompleteOrder $order, bool $includeColors = false): Collection
    {
        $results = Genre::query()
            ->select(['id', 'title'])
            ->withCount('products')
            ->where(fn(Builder $builder) => $this->matcher->whereWordPrefixMatch($builder, 'genres.title', $query))
            ->get()
            ->map(fn(Genre $genre): array => [
                'id' => $genre->getKey(),
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

        if (! $includeColors) {
            return $results->map(function (array $result): array {
                unset($result['id']);

                return $result;
            });
        }

        $colors = TagColor::effectiveColorPairsForGenreIds(
            $results->map(fn(array $result): int => (int) $result['id'])
        );

        return $results
            ->map(function (array $result) use ($colors): array {
                $color = $colors->get((int) $result['id']);
                unset($result['id']);

                return [
                    ...$result,
                    'color' => $color['color'] ?? null,
                    'text_color' => $color['text_color'] ?? null,
                ];
            });
    }
}
