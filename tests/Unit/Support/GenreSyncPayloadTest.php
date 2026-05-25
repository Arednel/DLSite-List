<?php

namespace Tests\Unit\Support;

use App\Models\Genre;
use App\Support\GenreSyncPayload;
use PHPUnit\Framework\TestCase;

class GenreSyncPayloadTest extends TestCase
{
    public function test_it_marks_fetched_and_custom_genre_sources(): void
    {
        $this->assertSame([
            10 => ['source' => Genre::PIVOT_SOURCE_FETCHED],
            20 => ['source' => Genre::PIVOT_SOURCE_CUSTOM],
        ], GenreSyncPayload::build([10], [20]));
    }

    public function test_it_deduplicates_genre_ids(): void
    {
        $this->assertSame([
            10 => ['source' => Genre::PIVOT_SOURCE_FETCHED],
            20 => ['source' => Genre::PIVOT_SOURCE_CUSTOM],
        ], GenreSyncPayload::build([10, 10], [20, 20]));
    }

    public function test_fetched_source_wins_when_a_genre_id_is_in_both_lists(): void
    {
        $this->assertSame([
            10 => ['source' => Genre::PIVOT_SOURCE_FETCHED],
            20 => ['source' => Genre::PIVOT_SOURCE_CUSTOM],
        ], GenreSyncPayload::build([10], [10, 20]));
    }

    public function test_it_builds_language_map_for_fetched_language_buckets(): void
    {
        $this->assertSame([
            10 => [Genre::LANGUAGE_JAPANESE, Genre::LANGUAGE_ENGLISH],
            20 => [Genre::LANGUAGE_ENGLISH],
        ], GenreSyncPayload::languageMap([
            Genre::LANGUAGE_JAPANESE => [10, 10],
            Genre::LANGUAGE_ENGLISH => [10, 20],
        ]));
    }
}
