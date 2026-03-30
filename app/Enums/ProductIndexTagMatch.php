<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductIndexTagMatch: string
{
    use ProvidesOptions;

    case All = 'all';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All tags',
            self::Any => 'Any tag',
        };
    }
}
