<?php

namespace App\Support\Transfers;

use App\Enums\LibraryTransferOperation;
use App\Jobs\LibraryTransferJob;
use App\Models\LibraryTransferRun;
use App\Support\LibraryMutationLock;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TransferJobDispatcher
{
    public function __construct(private readonly LibraryMutationLock $mutationLock) {}

    public function assertReady(): void
    {
        $connection = config('queue.default');
        $queue = config("queue.connections.{$connection}");
        if (
            $connection !== 'database' || ($queue['driver'] ?? null) !== 'database'
            || ($queue['connection'] ?? config('database.default')) !== config('database.default')
        ) {
            throw new RuntimeException('Library transfers require the database queue on the application database connection.');
        }
        if (
            config('transfers.job_timeout') < 1
            || config('transfers.job_timeout') >= config('transfers.lock_seconds')
            || max(config('transfers.lock_seconds'), $this->mutationLock->ttl()) >= ($queue['retry_after'] ?? 0)
        ) {
            throw new RuntimeException('Transfer timing must satisfy: worker timeout < lock lifetime < database queue retry_after.');
        }
    }

    /** Persist a guarded checkpoint and its successor job in the same database transaction. */
    public function checkpoint(
        LibraryTransferRun $run,
        array $attributes,
        ?LibraryTransferOperation $operation = null,
        ?int $partId = null,
    ): bool {
        return DB::transaction(function () use ($run, $attributes, $operation, $partId): bool {
            $current = LibraryTransferRun::whereKey($run->id)->lockForUpdate()->first();
            if (! $current || $current->generation !== $run->generation || $current->status !== $run->status) {
                return false;
            }

            $current->update($attributes);
            $token = 0;
            $uploadPath = null;
            if ($operation === LibraryTransferOperation::Inspect) {
                $part = $current->parts()->whereKey($partId)->lockForUpdate()->firstOrFail();
                $part->increment('validation_token');
                $token = $part->validation_token;
                $uploadPath = $part->candidate['path'] ?? $part->upload_path;
            } elseif ($operation !== null) {
                $current->increment('job_token');
                $token = $current->job_token;
            }

            if ($operation !== null) {
                LibraryTransferJob::dispatch(
                    runId: $current->id,
                    operation: $operation,
                    partId: $partId,
                    generation: $current->generation,
                    uploadPath: $uploadPath,
                    token: $token,
                )->onConnection('database')->beforeCommit();
            }

            $run->setRawAttributes($current->getAttributes(), true);

            return true;
        });
    }
}
