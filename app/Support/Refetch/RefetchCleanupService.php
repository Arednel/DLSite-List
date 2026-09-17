<?php

namespace App\Support\Refetch;

use App\Models\RefetchRun;
use App\Support\LibraryMutationLock;
use App\Support\ProductImagePromotion;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class RefetchCleanupService
{
    public const UNAVAILABLE_MESSAGE = 'Refetch cleanup is unavailable while a refetch run is running or cancelling.';

    public const BUSY_MESSAGE = 'Refetch cleanup is temporarily unavailable while another refetch action is in progress.';

    public const FAILED_MESSAGE = 'Refetch cleanup failed while removing staged files.';

    private const DIRECTORY = 'Refetch';

    private const DISKS = [
        'local',
        'public',
    ];

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
            $this->mutationLock->run(function (): void {
                $this->promotion->recover();
                DB::transaction(function (): void {
                    if ($this->activeRunQuery()->lockForUpdate()->first(['id']) !== null) {
                        throw new RuntimeException(self::UNAVAILABLE_MESSAGE);
                    }

                    RefetchRun::query()->delete();
                });

                foreach (self::DISKS as $disk) {
                    $this->clearDirectory(Storage::disk($disk));
                }
            });
        } catch (LockTimeoutException) {
            throw new RuntimeException(self::BUSY_MESSAGE);
        }
    }

    private function activeRunQuery(): Builder
    {
        return RefetchRun::query()->whereIn('status', [
            RefetchRun::STATUS_RUNNING,
            RefetchRun::STATUS_CANCELLING,
        ]);
    }

    private function clearDirectory(FilesystemAdapter $disk): void
    {
        try {
            $cleared = $disk->deleteDirectory(self::DIRECTORY)
                && $disk->makeDirectory(self::DIRECTORY);
        } catch (Throwable $exception) {
            throw new RuntimeException(self::FAILED_MESSAGE, previous: $exception);
        }

        if (! $cleared) {
            throw new RuntimeException(self::FAILED_MESSAGE);
        }
    }
}
