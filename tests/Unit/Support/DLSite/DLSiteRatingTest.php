<?php

namespace Tests\Unit\Support\DLSite;

use App\Support\DLSite\DLSiteRating;
use App\Support\DLSite\DLSiteWorkData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DLSiteRatingTest extends TestCase
{
    public function test_work_data_reads_the_scraper_rating_keys(): void
    {
        $work = DLSiteWorkData::fromArray([
            'japanese' => ['product_id' => 'RJ01741555', 'rate_average_2dp' => 4.96, 'rate_count' => 57],
            'english' => [],
        ]);

        $this->assertEquals(new DLSiteRating(4.96, 57), $work->rating);
        $this->assertSame(['dlsite_rating' => 4.96, 'dlsite_rating_count' => 57], $work->rating->attributes());
    }

    public function test_missing_keys_mean_the_rating_is_unknown(): void
    {
        $work = DLSiteWorkData::fromArray(['japanese' => ['product_id' => 'RJ01741555'], 'english' => []]);

        $this->assertNull($work->rating);
    }

    public function test_english_rating_is_used_when_japanese_has_none(): void
    {
        $work = DLSiteWorkData::fromArray([
            'japanese' => ['product_id' => 'RJ01741555'],
            'english' => ['rate_average_2dp' => '4.5', 'rate_count' => '12'],
        ]);

        $this->assertEquals(new DLSiteRating(4.5, 12), $work->rating);
    }

    #[DataProvider('unusableRatings')]
    public function test_unrated_or_invalid_values_store_no_rating(mixed $average, mixed $count): void
    {
        $this->assertEquals(new DLSiteRating(null, null), DLSiteRating::from($average, $count));
    }

    public static function unusableRatings(): array
    {
        return [
            'unrated work' => [null, null],
            'no ratings counted' => [4.5, 0],
            'average above five' => [5.01, 10],
            'negative average' => [-1, 10],
            'text average' => ['high', 10],
            'fractional count' => [4.5, 1.5],
            'negative count' => [4.5, -1],
            'boolean values' => [true, true],
            'average without count' => [4.5, null],
        ];
    }

    public function test_values_are_normalized_to_the_stored_shape(): void
    {
        $this->assertEquals(new DLSiteRating(5.0, 105), DLSiteRating::from(5, 105.0));
        $this->assertEquals(new DLSiteRating(4.97, 3), DLSiteRating::from('4.966', '3'));
        $this->assertSame(5.0, DLSiteRating::average(5));
    }
}
