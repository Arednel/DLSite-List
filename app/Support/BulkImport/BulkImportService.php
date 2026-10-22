<?php

namespace App\Support\BulkImport;

use App\Enums\BulkImportItemStatus;
use App\Enums\BulkImportRunStatus;
use App\Jobs\FinishBulkImportRunJob;
use App\Jobs\ImportBulkWorkJob;
use App\Models\BulkImportRun;
use App\Support\DLSite\DLSiteProductImportInput;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class BulkImportService
{
    public const BUSY_MESSAGE = 'Bulk Import cannot start while another Bulk Import action is in progress.';

    public const CAPACITY_MESSAGE = 'Bulk Import cannot start because too many works are already waiting to import. Wait for the current Bulk Imports to finish.';

    // Queued or running Bulk Import runs allowed at the same time.
    public const MAX_ACTIVE_RUNS = 3;

    // Works allowed to wait for import across all active runs, including the run being started.
    public const MAX_QUEUED_WORKS = 1000;

    /**
     * @param list<string> $productIds
     */
    public function start(
        array $productIds,
        DLSiteProductImportInput $input,
    ): BulkImportRun {
        try {
            return Cache::lock(BulkImportRun::LIFECYCLE_LOCK, BulkImportRun::LIFECYCLE_LOCK_SECONDS)
                ->block(0, function () use ($productIds, $input): BulkImportRun {
                    $this->assertCapacity(count($productIds));

                    $run = DB::transaction(function () use ($productIds, $input): BulkImportRun {
                        $run = BulkImportRun::create([
                            'status' => BulkImportRunStatus::Queued,
                            'input_snapshot' => $input->toSnapshot(),
                            'total_count' => count($productIds),
                        ]);

                        $run->items()->createMany(
                            collect($productIds)
                                ->values()
                                ->map(fn(string $productId, int $index): array => [
                                    'position' => $index + 1,
                                    'product_id' => $productId,
                                    'status' => BulkImportItemStatus::Pending,
                                ])
                                ->all(),
                        );

                        return $run;
                    });

                    try {
                        $jobs = $run->items()
                            ->orderBy('position')
                            ->get(['id'])
                            ->map(fn($item): ImportBulkWorkJob => new ImportBulkWorkJob($item->id))
                            ->all();
                        $jobs[] = new FinishBulkImportRunJob($run->id);

                        Bus::chain($jobs)
                            ->onConnection('database')
                            ->dispatch();
                    } catch (Throwable $exception) {
                        $run->forceFill([
                            'status' => BulkImportRunStatus::Failed,
                            'failed_at' => now(),
                            'error' => mb_substr($exception->getMessage(), 0, 2000),
                        ])->save();

                        throw $exception;
                    }

                    return $run->fresh();
                });
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException(self::BUSY_MESSAGE, previous: $exception);
        }
    }

    private function assertCapacity(int $newWorks): void
    {
        $activeRunIds = BulkImportRun::query()
            ->whereIn('status', [BulkImportRunStatus::Queued, BulkImportRunStatus::Running])
            ->pluck('id');

        if ($activeRunIds->count() >= self::MAX_ACTIVE_RUNS) {
            throw new BulkImportCapacityException(self::CAPACITY_MESSAGE);
        }

        $queuedWorks = BulkImportItem::query()
            ->whereIn('bulk_import_run_id', $activeRunIds)
            ->whereIn('status', [BulkImportItemStatus::Pending, BulkImportItemStatus::Importing])
            ->count();

        if ($queuedWorks + $newWorks > self::MAX_QUEUED_WORKS) {
            throw new BulkImportCapacityException(self::CAPACITY_MESSAGE);
        }
    }
}
