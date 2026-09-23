<?php

namespace App\Jobs;

use App\Enums\BulkImportItemStatus;
use App\Enums\BulkImportRunStatus;
use App\Models\BulkImportItem;
use App\Models\BulkImportRun;
use App\Models\Product;
use App\Support\DLSite\DLSiteProductAlreadyExistsException;
use App\Support\DLSite\DLSiteProductImportException;
use App\Support\DLSite\DLSiteProductImporter;
use App\Support\DLSite\DLSiteProductImportInput;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ImportBulkWorkJob implements ShouldQueue
{
    use Queueable;

    public const TIMEOUT_SECONDS = 3500;

    private const LOCK_SECONDS = 3600;

    public int $timeout = self::TIMEOUT_SECONDS;

    public int $tries = 0;

    public int $maxExceptions = 1;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $itemId) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('bulk-import-dlsite-work'))
                ->shared()
                ->releaseAfter(5)
                ->expireAfter(self::LOCK_SECONDS),
        ];
    }

    public function handle(DLSiteProductImporter $importer): void
    {
        $item = $this->markImporting();

        if (! $item) {
            return;
        }

        if (Product::query()->whereKey($item->product_id)->exists()) {
            $this->finish(
                BulkImportItemStatus::Skipped,
                error: 'Already in library.',
            );

            return;
        }

        try {
            $importer->import(
                $item->product_id,
                DLSiteProductImportInput::fromSnapshot($item->run->input_snapshot),
                fn(?string $warning) => $this->finish(
                    BulkImportItemStatus::Imported,
                    warning: $warning,
                ),
            );
        } catch (DLSiteProductAlreadyExistsException) {
            $this->finish(
                BulkImportItemStatus::Skipped,
                error: 'Already in library.',
            );

            return;
        } catch (DLSiteProductImportException $exception) {
            if (! $exception->isUnavailableWork()) {
                throw $exception;
            }

            $this->finish(
                BulkImportItemStatus::Failed,
                error: $exception->getMessage() ?: 'DLSite import failed.',
            );

            return;
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $item = BulkImportItem::query()->lockForUpdate()->find($this->itemId);

            if (! $item) {
                return;
            }

            $run = BulkImportRun::query()->lockForUpdate()->find($item->bulk_import_run_id);

            if (! $run) {
                return;
            }

            if (! $item->status->isTerminal()) {
                $item->forceFill([
                    'status' => BulkImportItemStatus::Failed,
                    'error' => mb_substr($exception?->getMessage() ?: 'Unexpected queue failure.', 0, 2000),
                    'completed_at' => now(),
                ])->save();

                $run->forceFill([
                    'processed_count' => $run->processed_count + 1,
                    'failed_count' => $run->failed_count + 1,
                ]);
            }

            $run->forceFill([
                'status' => BulkImportRunStatus::Failed,
                'failed_at' => now(),
                'error' => mb_substr($exception?->getMessage() ?: 'Bulk import worker stopped.', 0, 2000),
            ])->save();
        });

        Log::error('Bulk Import job failed.', [
            'item_id' => $this->itemId,
            'exception' => $exception,
        ]);
    }

    private function markImporting(): ?BulkImportItem
    {
        return DB::transaction(function (): ?BulkImportItem {
            $item = BulkImportItem::query()->lockForUpdate()->find($this->itemId);

            if (! $item || $item->status->isTerminal()) {
                return null;
            }

            $run = BulkImportRun::query()->lockForUpdate()->find($item->bulk_import_run_id);

            if (! $run || ! $run->status->isActive()) {
                return null;
            }

            $item->forceFill([
                'status' => BulkImportItemStatus::Importing,
                'started_at' => $item->started_at ?? now(),
                'warning' => null,
                'error' => null,
            ])->save();

            $run->forceFill([
                'status' => BulkImportRunStatus::Running,
                'started_at' => $run->started_at ?? now(),
            ])->save();

            return $item->fresh('run');
        });
    }

    private function finish(
        BulkImportItemStatus $status,
        ?string $warning = null,
        ?string $error = null,
    ): void {
        DB::transaction(function () use ($status, $warning, $error): void {
            $item = BulkImportItem::query()->lockForUpdate()->find($this->itemId);

            if (! $item || $item->status->isTerminal()) {
                if ($status === BulkImportItemStatus::Imported) {
                    throw new RuntimeException('Bulk Import item is no longer importing.');
                }

                return;
            }

            $run = BulkImportRun::query()->lockForUpdate()->findOrFail($item->bulk_import_run_id);

            if ($status === BulkImportItemStatus::Imported && (
                $item->status !== BulkImportItemStatus::Importing || ! $run->status->isActive()
            )) {
                throw new RuntimeException('Bulk Import item or run is no longer active.');
            }

            $item->forceFill([
                'status' => $status,
                'warning' => $warning,
                'error' => $error,
                'completed_at' => now(),
            ])->save();

            $changes = [
                'processed_count' => $run->processed_count + 1,
            ];

            match ($status) {
                BulkImportItemStatus::Imported => $changes['imported_count'] = $run->imported_count + 1,
                BulkImportItemStatus::Failed => $changes['failed_count'] = $run->failed_count + 1,
                BulkImportItemStatus::Skipped => $changes['skipped_count'] = $run->skipped_count + 1,
                default => null,
            };
            $run->forceFill($changes)->save();
        });
    }
}
