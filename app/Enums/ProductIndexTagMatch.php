<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductIndexTagMatch: string
{
    use ProvidesOptions;

    case Any = 'any';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Any => 'Any tag',
            self::All => 'All tags',
        };
    }
}
