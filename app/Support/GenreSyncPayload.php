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

    /**
     * @param  array<string, list<int|string>>  $fetchedGenreIdsByLanguage
     * @return array<int|string, list<string>>
     */
    public static function languageMap(array $fetchedGenreIdsByLanguage): array
    {
        $languageMap = [];

        foreach ($fetchedGenreIdsByLanguage as $language => $genreIds) {
            foreach (array_unique($genreIds) as $genreId) {
                $languageMap[$genreId] ??= [];
                $languageMap[$genreId][] = $language;
            }
        }

        foreach ($languageMap as $genreId => $languages) {
            $languageMap[$genreId] = array_values(array_unique($languages));
        }

        return $languageMap;
    }
}
