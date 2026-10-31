<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Five CSS stars filled to the nearest half star, followed by the number.
 *
 * The stars and the number are hidden from screen readers, which read the
 * description and ":value out of :max" instead.
 */
final class StarRating extends Component
{
    public const STARS = 5;

    /** Filled stars, rounded to the nearest half star (0-5). */
    public readonly float $stars;

    /** Width of the filled stars, in percent of all five. */
    public readonly int $fill;

    /** The value as shown, e.g. "4.96" or "9". */
    public readonly string $number;

    public function __construct(
        float|int $value,
        public int $max,
        public string $label = '',
        public string $description = '',
        int $decimals = 0,
    ) {
        $ratio = $max > 0 ? min(max($value / $max, 0), 1) : 0;

        $this->stars = round($ratio * self::STARS * 2) / 2;
        $this->fill = (int) round($this->stars / self::STARS * 100);
        $this->number = number_format($value, $decimals);
    }

    public function render(): View
    {
        return view('components.star-rating');
    }
}
