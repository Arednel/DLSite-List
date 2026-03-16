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
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }
}
