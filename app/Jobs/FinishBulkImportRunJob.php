<?php

namespace App\Jobs;

use App\Enums\BulkImportItemStatus;
use App\Enums\BulkImportRunStatus;
use App\Models\BulkImportRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FinishBulkImportRunJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $runId) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $run = BulkImportRun::query()->lockForUpdate()->find($this->runId);

            if (! $run || $run->status === BulkImportRunStatus::Failed) {
                return;
            }

            $counts = $run->items()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $imported = (int) ($counts[BulkImportItemStatus::Imported->value] ?? 0);
            $failed = (int) ($counts[BulkImportItemStatus::Failed->value] ?? 0);
            $skipped = (int) ($counts[BulkImportItemStatus::Skipped->value] ?? 0);

            $run->forceFill([
                'status' => BulkImportRunStatus::Completed,
                'processed_count' => $imported + $failed + $skipped,
                'imported_count' => $imported,
                'failed_count' => $failed,
                'skipped_count' => $skipped,
                'completed_at' => now(),
            ])->save();
        });
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $run = BulkImportRun::query()->lockForUpdate()->find($this->runId);

            if (! $run || in_array($run->status, [BulkImportRunStatus::Completed, BulkImportRunStatus::Failed], true)) {
                return;
            }

            $run->forceFill([
                'status' => BulkImportRunStatus::Failed,
                'failed_at' => now(),
                'error' => mb_substr($exception?->getMessage() ?: 'Bulk import finalization failed.', 0, 2000),
            ])->save();
        });

        Log::error('Bulk Import finalization failed.', [
            'run_id' => $this->runId,
            'exception' => $exception,
        ]);
    }
}
