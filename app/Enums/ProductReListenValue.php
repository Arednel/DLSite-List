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
            self::VeryLow => __('Very Low'),
            self::Low => __('Low'),
            self::Medium => __('Medium'),
            self::High => __('High'),
            self::VeryHigh => __('Very High'),
        };
    }
}
