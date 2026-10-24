<?php

namespace Tests\Unit\View\Components;

use App\View\Components\StarRating;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StarRatingTest extends TestCase
{
    /**
     * @return iterable<string, array{float|int, int, int, float, int, string}> value, max, decimals, stars, fill and number
     */
    public static function ratings(): iterable
    {
        yield 'DLsite average rounds up to five stars' => [4.96, 5, 2, 5.0, 100, '4.96'];
        yield 'DLsite average rounds down to a whole star' => [4.24, 5, 2, 4.0, 80, '4.24'];
        yield 'DLsite average rounds to a half star' => [4.25, 5, 2, 4.5, 90, '4.25'];
        yield 'whole DLsite average keeps two decimals' => [5, 5, 2, 5.0, 100, '5.00'];
        yield 'score of nine is four and a half stars' => [9, 10, 0, 4.5, 90, '9'];
        yield 'score of one is half a star' => [1, 10, 0, 0.5, 10, '1'];
        yield 'values above the maximum are capped' => [12, 10, 0, 5.0, 100, '12'];
        yield 'negative values show no stars' => [-1, 5, 0, 0.0, 0, '-1'];
        yield 'a zero maximum shows no stars' => [3, 0, 0, 0.0, 0, '3'];
    }

    #[DataProvider('ratings')]
    public function test_it_rounds_to_half_stars(float|int $value, int $max, int $decimals, float $stars, int $fill, string $number): void
    {
        $rating = new StarRating($value, $max, decimals: $decimals);

        $this->assertSame($stars, $rating->stars);
        $this->assertSame($fill, $rating->fill);
        $this->assertSame($number, $rating->number);
    }

    public function test_it_renders_css_stars_with_screen_reader_text(): void
    {
        $this->blade('<x-star-rating class="extra" :value="9" :max="10" label="Mine" description="Your Score" />')
            ->assertSee('class="star-rating extra"', false)
            ->assertSee('<span class="star-rating__label" aria-hidden="true">Mine</span>', false)
            ->assertSee('<span class="star-rating__stars" style="--star-rating-fill: 90%" aria-hidden="true"></span>', false)
            ->assertSee('<span class="star-rating__number" aria-hidden="true">9</span>', false)
            ->assertSee('<span class="star-rating__description">Your Score 9 out of 10</span>', false)
            ->assertDontSee('<img', false);
    }

    public function test_it_omits_the_label_when_none_is_given(): void
    {
        $this->blade('<x-star-rating :value="4.5" :max="5" :decimals="2" />')
            ->assertDontSee('star-rating__label', false)
            ->assertSee('<span class="star-rating__description">4.50 out of 5</span>', false);
    }

    public function test_screen_reader_text_is_translated(): void
    {
        app()->setLocale('ja');
        $this->assertSame('5点中4.96点', __(':value out of :max', ['value' => '4.96', 'max' => 5]));

        app()->setLocale('zh_TW');
        $this->assertSame('9 分（滿分 10 分）', __(':value out of :max', ['value' => '9', 'max' => 10]));
    }
}
