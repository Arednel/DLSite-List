<?php

namespace Tests\Unit\Support\DLSite;

use App\Enums\RatingScale;
use App\Support\DLSite\DLSiteRating;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DLSiteRatingTest extends TestCase
{
    public function test_distribution_calculates_both_scales_independently_before_rounding(): void
    {
        $rating = DLSiteRating::fromDistribution([
            '5' => 966,
            '4' => 34,
            '3' => 0,
            '2' => 0,
            '1' => 0,
        ]);

        $this->assertSame('4.97', $rating->rating5);
        $this->assertSame('9.93', $rating->rating10);
        $this->assertSame(1000, $rating->count);
    }

    public function test_known_zero_votes_keeps_count_zero_without_inventing_a_rating(): void
    {
        $rating = DLSiteRating::fromDistribution([
            '5' => 0,
            '4' => 0,
            '3' => 0,
            '2' => 0,
            '1' => 0,
        ]);

        $this->assertNull($rating->rating5);
        $this->assertNull($rating->rating10);
        $this->assertSame(0, $rating->count);
    }

    public function test_missing_distribution_remains_unknown(): void
    {
        $rating = DLSiteRating::fromDistribution(null);

        $this->assertNull($rating->rating5);
        $this->assertNull($rating->rating10);
        $this->assertNull($rating->count);
    }

    public function test_manual_rating_follows_selected_scale_and_stores_the_other_scale_once(): void
    {
        $tenPoint = DLSiteRating::fromManual('7.36', '123', RatingScale::Ten);
        $this->assertSame('3.68', $tenPoint->rating5);
        $this->assertSame('7.36', $tenPoint->rating10);
        $this->assertSame(123, $tenPoint->count);

        $fivePoint = DLSiteRating::fromManual('4.97', 456, RatingScale::Five);
        $this->assertSame('4.97', $fivePoint->rating5);
        $this->assertSame('9.94', $fivePoint->rating10);
        $this->assertSame(456, $fivePoint->count);
    }

    public function test_manual_rating_accepts_independently_nullable_rating_and_count(): void
    {
        $countOnly = DLSiteRating::fromManual(null, '25', RatingScale::Five);
        $this->assertNull($countOnly->rating5);
        $this->assertNull($countOnly->rating10);
        $this->assertSame(25, $countOnly->count);

        $ratingOnly = DLSiteRating::fromManual('0.25', null, RatingScale::Five);
        $this->assertSame('0.25', $ratingOnly->rating5);
        $this->assertSame('0.50', $ratingOnly->rating10);
        $this->assertNull($ratingOnly->count);

        $zero = DLSiteRating::fromManual('0', '0', RatingScale::Five);
        $this->assertSame('0.00', $zero->rating5);
        $this->assertSame('0.00', $zero->rating10);
        $this->assertSame(0, $zero->count);
    }

    public function test_manual_rating_rejects_values_above_the_selected_scale(): void
    {
        $this->expectException(ValidationException::class);
        DLSiteRating::fromManual('5.01', null, RatingScale::Five);
    }

    public function test_manual_rating_rejects_more_than_two_decimal_places(): void
    {
        $this->expectException(ValidationException::class);
        DLSiteRating::fromManual('4.971', null, RatingScale::Five);
    }

    public function test_vote_count_uses_shared_laravel_unsigned_integer_validation(): void
    {
        $maximum = DLSiteRating::fromManual(null, '4294967295', RatingScale::Five);
        $this->assertSame(4294967295, $maximum->count);

        $this->expectException(ValidationException::class);
        DLSiteRating::fromManual(null, '4294967296', RatingScale::Five);
    }

    public function test_distribution_rejects_vote_totals_outside_the_shared_count_rules(): void
    {
        $rating = DLSiteRating::fromDistribution([
            '5' => 4294967295,
            '4' => 1,
            '3' => 0,
            '2' => 0,
            '1' => 0,
        ]);

        $this->assertNull($rating->rating5);
        $this->assertNull($rating->rating10);
        $this->assertNull($rating->count);
    }

    public function test_vote_count_display_is_localized_and_preserves_zero(): void
    {
        foreach (['en', 'ja', 'zh_TW'] as $locale) {
            app()->setLocale($locale);

            $this->assertSame(
                $locale === 'en' ? '(1,330 votes)' : '(1,330 票)',
                DLSiteRating::displayVoteCount(1330),
            );
            $this->assertSame(
                $locale === 'en' ? '(1 vote)' : '(1 票)',
                DLSiteRating::displayVoteCount(1),
            );
            $this->assertSame(
                $locale === 'en' ? '(0 votes)' : '(0 票)',
                DLSiteRating::displayVoteCount(0),
            );
            $this->assertNull(DLSiteRating::displayVoteCount(null));
        }
    }

    public function test_display_value_uses_at_least_one_and_at_most_two_decimal_places(): void
    {
        $this->assertSame('5.0', DLSiteRating::displayValue('5.00'));
        $this->assertSame('0.0', DLSiteRating::displayValue('0'));
        $this->assertSame('4.9', DLSiteRating::displayValue('4.90'));
        $this->assertSame('4.97', DLSiteRating::displayValue('4.97'));
        $this->assertNull(DLSiteRating::displayValue(null));
    }
}
