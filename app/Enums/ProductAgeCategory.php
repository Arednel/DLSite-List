<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductAgeCategory: string
{
    use ProvidesOptions;

    case AllAges = 'ALL_AGES';
    case R15 = 'R15';
    case R18 = 'R18';

    public function label(): string
    {
        return match ($this) {
            self::AllAges => 'All Ages',
            self::R15 => 'R15',
            self::R18 => 'R18',
        };
    }
}
