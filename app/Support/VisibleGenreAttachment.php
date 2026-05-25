<?php

namespace App\Support;

use App\Models\Genre;
use Closure;

final class VisibleGenreAttachment
{
    public static function query(): Closure
    {
        return function ($query): void {
            $query->where(function ($visibleQuery): void {
                $visibleQuery->where('genre_product.source', Genre::PIVOT_SOURCE_CUSTOM)
                    ->orWhereExists(function ($languageQuery): void {
                        $languageQuery->select('genre_product_languages.id')
                            ->from('genre_product_languages')
                            ->whereColumn('genre_product_languages.genre_product_id', 'genre_product.id')
                            ->where('genre_product_languages.language', Genre::LANGUAGE_ENGLISH);
                    });
            });
        };
    }
}
