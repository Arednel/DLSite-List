<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductIndexSortDirection: string
{
    use ProvidesOptions;

    case Asc = 'asc';
    case Desc = 'desc';

    public function label(): string
    {
        return match ($this) {
            self::Asc => 'Asc',
            self::Desc => 'Desc',
        };
    }
}
