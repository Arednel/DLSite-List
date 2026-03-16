<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductScore: string
{
    use ProvidesOptions;

    case One = '1';
    case Two = '2';
    case Three = '3';
    case Four = '4';
    case Five = '5';
    case Six = '6';
    case Seven = '7';
    case Eight = '8';
    case Nine = '9';
    case Ten = '10';

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
