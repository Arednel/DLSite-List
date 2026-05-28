<?php

namespace App\Support\Autocomplete;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

class AutocompleteMatcher
{
    private const WORD_SEPARATORS = [
        ' ',
        ',',
        '/',
        '-',
        '_',
        '(',
        ')',
        '[',
        ']',
    ];

    public function whereWordPrefixMatch(Builder|EloquentBuilder $query, string $column, string $value): void
    {
        $escapedValue = $this->escapeLike($value);

        $query->where($column, 'like', "{$escapedValue}%");

        foreach (self::WORD_SEPARATORS as $separator) {
            $query->orWhere($column, 'like', '%' . $this->escapeLike($separator) . "{$escapedValue}%");
        }

        if ($this->containsNonAscii($value)) {
            $query->orWhere($column, 'like', "%{$escapedValue}%");
        }
    }

    public function matchRank(string $value, string $query): int
    {
        $normalizedValue = mb_strtolower($value);
        $normalizedQuery = mb_strtolower($query);

        if (str_starts_with($normalizedValue, $normalizedQuery)) {
            return 0;
        }

        foreach (self::WORD_SEPARATORS as $separator) {
            if (str_contains($normalizedValue, $separator . $normalizedQuery)) {
                return 1;
            }
        }

        if ($this->containsNonAscii($query) && str_contains($normalizedValue, $normalizedQuery)) {
            return 2;
        }

        return 3;
    }

    /**
     * @param  array{value: string, label: string, count: int, type: string}  $left
     * @param  array{value: string, label: string, count: int, type: string}  $right
     */
    public function compareSuggestions(array $left, array $right, string $query, bool $useFirstWordRank): int
    {
        if ($useFirstWordRank) {
            $rankComparison = $this->matchRank($left['value'], $query) <=> $this->matchRank($right['value'], $query);

            if ($rankComparison !== 0) {
                return $rankComparison;
            }
        }

        $countComparison = $right['count'] <=> $left['count'];

        if ($countComparison !== 0) {
            return $countComparison;
        }

        return mb_strtolower($left['label']) <=> mb_strtolower($right['label']);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function containsNonAscii(string $value): bool
    {
        return preg_match('/[^\x00-\x7F]/u', $value) === 1;
    }
}
