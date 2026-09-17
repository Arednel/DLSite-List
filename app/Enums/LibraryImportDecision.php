<?php

namespace App\Enums;

enum LibraryImportDecision: string
{
    case Ignore = 'ignore';
    case Overwrite = 'overwrite';
    case Merge = 'merge';

    public function forNewWork(): self
    {
        return $this === self::Overwrite ? self::Merge : $this;
    }
}
