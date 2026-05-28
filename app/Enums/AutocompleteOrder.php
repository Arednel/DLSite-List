<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum AutocompleteOrder: string
{
    use ProvidesOptions;

    case Usage = 'usage';
    case FirstWord = 'first_word';

    public function label(): string
    {
        return match ($this) {
            self::Usage => 'Most used first',
            self::FirstWord => 'First word first',
        };
    }
}
