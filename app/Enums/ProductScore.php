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
            self::Ten => '(10) Masterpiece',
            self::Nine => '(9) Great',
            self::Eight => '(8) Very Good',
            self::Seven => '(7) Good',
            self::Six => '(6) Nice',
            self::Five => '(5) Average',
            self::Four => '(4) Below Average',
            self::Three => '(3) Unremarkable',
            self::Two => '(2) Subtle',
            self::One => '(1) Faint',
        };
    }
}
