<?php

namespace App\Enums;

enum BulkImportRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Running], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('Queued'),
            self::Running => __('Running'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed'),
        };
    }
}
