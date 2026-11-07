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
                            throw new RuntimeException('bulk_import.cleanup.unavailable');
                        }

                        BulkImportRun::query()->delete();
                    });
                });
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException('bulk_import.cleanup.busy', previous: $exception);
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
