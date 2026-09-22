<?php

namespace App\Support\BulkImport;

use App\Enums\BulkImportRunStatus;
use App\Models\BulkImportRun;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BulkImportCleanupService
{
    public const UNAVAILABLE_MESSAGE = 'Bulk Import history cleanup is unavailable while a Bulk Import is queued or running.';

    public const BUSY_MESSAGE = 'Bulk Import history cleanup is temporarily unavailable while another Bulk Import action is in progress.';

    public function unavailable(): bool
    {
        return $this->activeRunQuery()->exists();
    }

    public function cleanup(): void
    {
        try {
            Cache::lock(BulkImportRun::LIFECYCLE_LOCK, BulkImportRun::LIFECYCLE_LOCK_SECONDS)
                ->block(0, function (): void {
                    DB::transaction(function (): void {
                        if ($this->activeRunQuery()->lockForUpdate()->first(['id']) !== null) {
                            throw new RuntimeException(self::UNAVAILABLE_MESSAGE);
                        }

                        BulkImportRun::query()->delete();
                    });
                });
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException(self::BUSY_MESSAGE, previous: $exception);
        }
    }

    private function activeRunQuery(): Builder
    {
        return BulkImportRun::query()->whereIn('status', [
            BulkImportRunStatus::Queued->value,
            BulkImportRunStatus::Running->value,
        ]);
    }
}
