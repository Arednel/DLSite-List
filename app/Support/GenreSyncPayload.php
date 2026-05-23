<?php

namespace App\Support;

use App\Models\Genre;

final class GenreSyncPayload
{
    /**
     * @param  list<int|string>  $fetchedGenreIds
     * @param  list<int|string>  $customGenreIds
     * @return array<int|string, array{source: string}>
     */
    public static function build(array $fetchedGenreIds, array $customGenreIds): array
    {
        $payload = [];

        foreach (array_unique($fetchedGenreIds) as $genreId) {
            $payload[$genreId] = ['source' => Genre::PIVOT_SOURCE_FETCHED];
        }

        foreach (array_unique($customGenreIds) as $genreId) {
            $payload[$genreId] ??= ['source' => Genre::PIVOT_SOURCE_CUSTOM];
        }

        return $payload;
    }
}
