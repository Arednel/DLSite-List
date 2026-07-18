<?php

namespace App\Support;

use App\Enums\UiLanguage;
use App\Models\Genre;
use Closure;

final class VisibleGenreAttachment
{
    public static function query(?string $fetchedLanguage = null): Closure
    {
        $fetchedLanguage ??= UiLanguage::current()->fetchedTagLanguage();

        return function ($query) use ($fetchedLanguage): void {
            $query->where(function ($visibleQuery) use ($fetchedLanguage): void {
                $visibleQuery->where('genre_product.source', Genre::PIVOT_SOURCE_CUSTOM)
                    ->orWhere(function ($fetchedQuery) use ($fetchedLanguage): void {
                        $fetchedQuery->where('genre_product.source', Genre::PIVOT_SOURCE_FETCHED)
                            ->whereExists(function ($languageQuery) use ($fetchedLanguage): void {
                                $languageQuery->select('genre_product_languages.id')
                                    ->from('genre_product_languages')
                                    ->whereColumn('genre_product_languages.genre_product_id', 'genre_product.id')
                                    ->where('genre_product_languages.language', $fetchedLanguage);
                            });
                    });
            });
        };
    }
}
