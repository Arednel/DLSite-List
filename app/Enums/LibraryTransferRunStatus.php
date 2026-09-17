<?php

namespace App\Enums;

enum LibraryTransferRunStatus: string
{
    case Queued = 'queued';
    case Planning = 'planning';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Building = 'building';
    case Ready = 'ready';
    case Uploading = 'uploading';
    case WaitingForParts = 'waiting_for_parts';
    case Analyzing = 'analyzing';
    case Review = 'review';
    case Applying = 'applying';
    case Completed = 'completed';
    case CompletedWithWarnings = 'completed_with_warnings';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function isAwaitingConfirmation(): bool
    {
        return $this === self::AwaitingConfirmation;
    }

    public function isWaitingForParts(): bool
    {
        return $this === self::WaitingForParts;
    }

    public function isReady(): bool
    {
        return $this === self::Ready;
    }

    public function isRetryable(): bool
    {
        return in_array($this, [self::Failed, self::WaitingForParts], true);
    }

    public function isCancellable(): bool
    {
        return ! in_array($this, [
            self::Applying,
            self::Completed,
            self::CompletedWithWarnings,
            self::Ready,
            self::Cancelled,
            self::Failed,
        ], true);
    }

    public function isBusy(): bool
    {
        return in_array($this, [self::Queued, self::Planning, self::Building, self::Analyzing, self::Applying], true);
    }

    public function isReviewVisible(): bool
    {
        return in_array($this, [self::Review, self::Completed, self::CompletedWithWarnings], true);
    }

    public function isCleanupActive(): bool
    {
        return in_array($this, [
            self::Queued,
            self::Planning,
            self::Building,
            self::Uploading,
            self::WaitingForParts,
            self::Analyzing,
            self::Applying,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::CompletedWithWarnings, self::Ready], true);
    }
}
