<?php

namespace App\Support;

use App\Enums\FetchedTagLanguage;
use App\Models\Genre;
use App\Models\Option;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class VisibleGenreAttachment
{
    public static function query(?string $fetchedLanguage = null): Closure
    {
        $languages = self::languageOrder($fetchedLanguage ?? Option::effectiveFetchedTagLanguage()->value);

        return function ($query) use ($languages): void {
            $query->where(function ($visibleQuery) use ($languages): void {
                $visibleQuery->where('genre_product.source', Genre::PIVOT_SOURCE_CUSTOM)
                    ->orWhere(function ($fetchedQuery) use ($languages): void {
                        $fetchedQuery->where('genre_product.source', Genre::PIVOT_SOURCE_FETCHED)
                            ->where(function ($languageQuery) use ($languages): void {
                                foreach ($languages as $index => $language) {
                                    // A fallback language only applies to works without fetched rows in any earlier language.
                                    $languageQuery->orWhere(function ($candidateQuery) use ($languages, $index, $language): void {
                                        $candidateQuery->whereExists(function ($attachmentQuery) use ($language): void {
                                            $attachmentQuery->select('genre_product_languages.id')
                                                ->from('genre_product_languages')
                                                ->whereColumn('genre_product_languages.genre_product_id', 'genre_product.id')
                                                ->where('genre_product_languages.language', $language);
                                        });

                                        foreach (array_slice($languages, 0, $index) as $preferredLanguage) {
                                            $candidateQuery->whereNotExists(function ($productQuery) use ($preferredLanguage): void {
                                                self::fetchedLanguageRows($productQuery, [$preferredLanguage])
                                                    ->whereColumn('preferred_pivots.product_id', 'genre_product.product_id');
                                            });
                                        }
                                    });
                                }
                            });
                    });
            });
        };
    }

    /**
     * The fetched language a work actually shows, so editing writes back to the rows on screen.
     */
    public static function resolvedLanguage(string $productId, ?string $fetchedLanguage = null): string
    {
        $languages = self::languageOrder($fetchedLanguage ?? Option::effectiveFetchedTagLanguage()->value);

        if (count($languages) === 1) {
            return $languages[0];
        }

        $available = self::fetchedLanguageRows(DB::query(), $languages)
            ->where('preferred_pivots.product_id', $productId)
            ->distinct()
            ->pluck('preferred_languages.language')
            ->all();

        foreach ($languages as $language) {
            if (in_array($language, $available, true)) {
                return $language;
            }
        }

        return $languages[0];
    }

    /**
     * @return list<string>
     */
    private static function languageOrder(string $fetchedLanguage): array
    {
        $language = FetchedTagLanguage::tryFrom($fetchedLanguage);

        if ($language === null) {
            return [$fetchedLanguage];
        }

        return array_map(
            fn(FetchedTagLanguage $fallback): string => $fallback->value,
            $language->fallbackOrder(),
        );
    }

    /**
     * @param  list<string>  $languages
     */
    private static function fetchedLanguageRows(Builder $query, array $languages): Builder
    {
        return $query->select('preferred_languages.language')
            ->from('genre_product as preferred_pivots')
            ->join(
                'genre_product_languages as preferred_languages',
                'preferred_languages.genre_product_id',
                '=',
                'preferred_pivots.id',
            )
            ->where('preferred_pivots.source', Genre::PIVOT_SOURCE_FETCHED)
            ->whereIn('preferred_languages.language', $languages);
    }
}
