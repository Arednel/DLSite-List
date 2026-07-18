<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductScore: string
{
    use ProvidesOptions;

        // Explicit ordering from 10 to 1
    case Ten = '10';
    case Nine = '9';
    case Eight = '8';
    case Seven = '7';
    case Six = '6';
    case Five = '5';
    case Four = '4';
    case Three = '3';
    case Two = '2';
    case One = '1';

    public function label(): string
    {
        return match ($this) {
            self::Ten => __('(10) Masterpiece'),
            self::Nine => __('(9) Great'),
            self::Eight => __('(8) Very Good'),
            self::Seven => __('(7) Good'),
            self::Six => __('(6) Nice'),
            self::Five => __('(5) Average'),
            self::Four => __('(4) Below Average'),
            self::Three => __('(3) Unremarkable'),
            self::Two => __('(2) Subtle'),
            self::One => __('(1) Faint'),
        };
    }
}
