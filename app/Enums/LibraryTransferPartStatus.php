<?php

namespace App\Enums;

enum LibraryTransferPartStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Excluded = 'excluded';
    case Ready = 'ready';

    public function isInvalid(): bool
    {
        return $this === self::Invalid;
    }
}
