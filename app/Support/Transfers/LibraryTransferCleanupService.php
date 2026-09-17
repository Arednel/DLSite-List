<?php

namespace App\Support\Transfers;

use App\Models\LibraryTransferRun;
use App\Support\LibraryMutationLock;
use App\Support\ProductImagePromotion;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class LibraryTransferCleanupService
{
    public const UNAVAILABLE_MESSAGE = 'Transfer history cleanup is unavailable while an import or export is in progress.';

    public const BUSY_MESSAGE = 'Transfer history cleanup is temporarily unavailable while another transfer action is in progress.';

    public const FAILED_MESSAGE = 'Transfer history cleanup failed while removing staged files.';

    private const DIRECTORY = 'Transfers';

    public function __construct(
        private readonly LibraryMutationLock $mutationLock,
        private readonly ProductImagePromotion $promotion,
    ) {}

    public function unavailable(): bool
    {
        return $this->activeRunQuery()->exists();
    }

    public function cleanup(): void
    {
        try {
            Cache::lock(LibraryTransferRun::LIFECYCLE_LOCK, config('transfers.lock_seconds'))
                ->block(0, function (): void {
                    $locks = $this->acquireRunLocks();

                    try {
                        $this->mutationLock->run(function (): void {
                            $this->promotion->recover();
                            DB::transaction(function (): void {
                                if ($this->activeRunQuery()->lockForUpdate()->first(['id']) !== null) {
                                    throw new RuntimeException(self::UNAVAILABLE_MESSAGE);
                                }

                                $this->deleteFiles();
                                LibraryTransferRun::query()->delete();
                            });
                        });
                    } finally {
                        foreach (array_reverse($locks) as $lock) {
                            $lock->release();
                        }
                    }
                });
        } catch (LockTimeoutException) {
            throw new RuntimeException(self::BUSY_MESSAGE);
        }
    }

    private function activeRunQuery(): Builder
    {
        return LibraryTransferRun::query()->activeForCleanup();
    }

    /** @return list<Lock> */
    private function acquireRunLocks(): array
    {
        $locks = [];

        try {
            foreach (LibraryTransferRun::query()->orderBy('id')->pluck('id') as $runId) {
                foreach ($this->lockNames((int) $runId) as $name) {
                    $lock = Cache::lock($name, config('transfers.lock_seconds'));
                    if (! $lock->get()) {
                        throw new RuntimeException(self::BUSY_MESSAGE);
                    }
                    $locks[] = $lock;
                }
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }

            throw $exception;
        }

        return $locks;
    }

    /** @return list<string> */
    private function lockNames(int $runId): array
    {
        return ["transfer-run-{$runId}", "transfer-upload-{$runId}"];
    }

    private function deleteFiles(): void
    {
        try {
            $disk = Storage::disk('local');
            if ($disk->exists(self::DIRECTORY) && ! $disk->deleteDirectory(self::DIRECTORY)) {
                throw new RuntimeException(self::FAILED_MESSAGE);
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(self::FAILED_MESSAGE, previous: $exception);
        }
    }
}
