<?php

namespace App\Jobs;

use App\Enums\LibraryTransferOperation;
use App\Enums\LibraryTransferPartStatus;
use App\Enums\LibraryTransferRunStatus;
use App\Models\LibraryTransferRun;
use App\Support\Transfers\InvalidArchive;
use App\Support\Transfers\LibraryTransferService;
use App\Support\Transfers\SourceChanged;
use App\Support\Transfers\TransferArchive;
use App\Support\Transfers\TransferJobDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

class LibraryTransferJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $runId,
        public LibraryTransferOperation $operation,
        public ?int $partId = null,
        public int $generation = 1,
        public ?string $uploadPath = null,
        public int $token = 0,
    ) {
        $this->timeout = config('transfers.job_timeout');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('transfer-run-' . $this->runId))
                ->shared()
                ->withPrefix('')
                ->releaseAfter(10)
                ->expireAfter(config('transfers.lock_seconds')),
        ];
    }

    public function handle(TransferArchive $archive, LibraryTransferService $transfers, TransferJobDispatcher $dispatcher): void
    {
        $run = LibraryTransferRun::find($this->runId);
        if (! $run) {
            return;
        }

        $staleGeneration = $run->generation !== $this->generation;
        $stoppedRun = in_array($run->status, [LibraryTransferRunStatus::Cancelled, LibraryTransferRunStatus::Failed], true);
        $terminalRun = $this->operation !== LibraryTransferOperation::Inspect && $run->status->isTerminal();
        if ($staleGeneration || $stoppedRun || $terminalRun) {
            return;
        }
        if ($this->operation === LibraryTransferOperation::Inspect) {
            $part = $run->parts()->find($this->partId);
            $uploadMatches = $part
                && ($this->uploadPath === $part->upload_path || $this->uploadPath === ($part->candidate['path'] ?? null));
            if (! $part || $part->validation_token !== $this->token || ! $uploadMatches) {
                return;
            }
        } elseif ($run->job_token !== $this->token) {
            return;
        }
        try {
            match ($this->operation) {
                LibraryTransferOperation::Plan => in_array($run->status, [LibraryTransferRunStatus::Queued, LibraryTransferRunStatus::Planning], true) ? $archive->plan($run) : null,
                LibraryTransferOperation::Build => ($part = $run->parts()->find($this->partId)) ? $archive->build($part) : null,
                LibraryTransferOperation::Inspect => $transfers->inspect($run, $this->partId, $this->uploadPath),
                LibraryTransferOperation::Analyze => $transfers->analyze($run),
                LibraryTransferOperation::Apply => $transfers->apply($run),
            };
        } catch (SourceChanged $exception) {
            $replans = ($run->settings['replans'] ?? 0) + 1;
            if ($replans > 2) {
                throw $exception;
            }
            $dispatcher->checkpoint($run, [
                'status' => LibraryTransferRunStatus::Queued,
                'generation' => $run->generation + 1,
                'settings' => [...$run->settings, 'replans' => $replans, 'planning' => null],
            ], LibraryTransferOperation::Plan);
        } catch (InvalidArchive $exception) {
            $this->fail($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $run = LibraryTransferRun::whereKey($this->runId)->lockForUpdate()->first();
            if (! $run || $run->generation !== $this->generation || $run->status === LibraryTransferRunStatus::Cancelled) {
                return;
            }
            if ($this->operation === LibraryTransferOperation::Inspect) {
                $part = $run->parts()->whereKey($this->partId)->lockForUpdate()->first();
                if (! $part || $part->validation_token !== $this->token || ($part->kind === 'images' && ($run->settings['without_images'] ?? false))) {
                    return;
                }
                if ($part->status === LibraryTransferPartStatus::Valid) {
                    if ($this->uploadPath !== null && ($part->candidate['path'] ?? null) === $this->uploadPath) {
                        $part->update(['candidate' => null, 'error' => 'Duplicate validation failed. The accepted part is retained; upload the duplicate again to retry.']);
                    }

                    return;
                }
            } elseif ($run->job_token !== $this->token) {
                return;
            }
            $expected = $this->operation->expectedStatuses();
            if (! in_array($run->status, $expected, true)) {
                return;
            }
            $imageFailure = $this->operation === LibraryTransferOperation::Inspect && $part?->kind === 'images';
            if ($imageFailure) {
                $run->parts()->whereKey($this->partId)->update(['status' => LibraryTransferPartStatus::Invalid, 'error' => mb_substr($exception?->getMessage() ?? 'Image validation worker stopped.', 0, 2000)]);
            }
            $run->update([
                'status' => $imageFailure ? LibraryTransferRunStatus::WaitingForParts : LibraryTransferRunStatus::Failed,
                'error' => mb_substr($exception?->getMessage() ?? 'Transfer worker stopped.', 0, 2000),
                'settings' => [
                    ...$run->settings,
                    'retry_operation' => $exception instanceof InvalidArchive ? null : ($exception instanceof SourceChanged ? LibraryTransferOperation::Plan->value : $this->operation->value),
                    'retry_part' => $exception instanceof SourceChanged ? null : $this->partId,
                    'retry_status' => $exception instanceof SourceChanged ? LibraryTransferRunStatus::Queued->value : $run->status->value,
                    ...($exception instanceof SourceChanged ? ['planning' => null, 'replans' => 0] : []),
                ],
            ]);
        });
    }
}
