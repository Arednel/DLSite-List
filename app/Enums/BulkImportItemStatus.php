<?php

namespace App\Enums;

enum BulkImportItemStatus: string
{
    case Pending = 'pending';
    case Importing = 'importing';
    case Imported = 'imported';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Imported, self::Failed, self::Skipped], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Importing => __('Importing'),
            self::Imported => __('Imported'),
            self::Failed => __('Failed'),
            self::Skipped => __('Skipped'),
        };
    }
}
