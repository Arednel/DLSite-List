<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductPriority: string
{
    use ProvidesOptions;

    case Low = '0';
    case Medium = '1';
    case High = '2';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Medium => __('Medium'),
            self::High => __('High'),
        };
    }
}
