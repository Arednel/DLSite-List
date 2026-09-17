<?php

namespace App\Enums;

enum LibraryImportItemStatus: string
{
    case Pending = 'pending';
    case Unavailable = 'unavailable';
    case Ignored = 'ignored';
    case Applied = 'applied';
    case Conflict = 'conflict';
    case Failed = 'failed';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
