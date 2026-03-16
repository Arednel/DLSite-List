<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductReListenValue: string
{
    use ProvidesOptions;

    case VeryLow = '1';
    case Low = '2';
    case Medium = '3';
    case High = '4';
    case VeryHigh = '5';

    public function label(): string
    {
        return match ($this) {
            self::VeryLow => 'Very Low',
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::VeryHigh => 'Very High',
        };
    }
}
