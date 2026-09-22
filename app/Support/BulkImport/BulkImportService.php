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
            throw new RuntimeException(__(self::BUSY_MESSAGE), previous: $exception);
        }
    }
}
