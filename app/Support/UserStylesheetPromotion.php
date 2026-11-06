<?php

namespace App\Support;

use App\Models\LibraryImportItem;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class UserStylesheetPromotion
{
    private const JOURNAL = 'UserStylesheetPromotions/active.json';

    private const MUTATION_LOCK = 'user-stylesheet-mutation';

    private const MUTATION_LOCK_SECONDS = 10;

    private const MUTATION_LOCK_WAIT_SECONDS = 10;

    private const RESULT_KEY = '_user_stylesheet_promotion';

    /**
     * @var array{
     *     token: string,
     *     item_id: int,
     *     backup: string,
     *     published: bool,
     *     published_sha256?: string
     * }|null
     */
    private ?array $journal = null;

    public function __construct(private readonly UserStylesheet $stylesheet) {}

    /** Replace user.css after reconciling any interrupted import publication. */
    public function save(string $css): void
    {
        $this->withMutationLock(function () use ($css): void {
            $this->recoverUnlocked();
            $this->stylesheet->save($css);
        });
    }

    public function reset(): void
    {
        $this->save('');
    }

    /** Database item state and user.css commit together; the file has a durable undo journal. */
    public function transaction(Closure $callback, LibraryImportItem $owner): mixed
    {
        if ($this->journal !== null) {
            throw new RuntimeException('Custom CSS promotion transaction is already active.');
        }

        $this->recover();

        $token = bin2hex(random_bytes(16));
        $this->journal = [
            'token' => $token,
            'item_id' => (int) $owner->getKey(),
            'backup' => 'UserStylesheetPromotions/' . $token . '/user.css',
            'published' => false,
        ];

        try {
            $result = DB::transaction(function () use ($callback, $owner, $token): mixed {
                $result = $callback();

                if ($this->journal['published']) {
                    $owner->refresh();
                    $owner->forceFill([
                        'result' => [...($owner->result ?? []), self::RESULT_KEY => $token],
                    ])->save();
                }

                return $result;
            });

            $published = $this->journal['published'];
        } catch (Throwable $exception) {
            $this->journal = null;
            try {
                $local = Storage::disk('local');
                if ($local->exists(self::JOURNAL)) {
                    $this->recover(false);
                } else {
                    $local->delete(self::JOURNAL . '.next');
                    $backupDirectory = 'UserStylesheetPromotions/' . $token;
                    if ($local->exists($backupDirectory) && ! $local->deleteDirectory($backupDirectory)) {
                        throw new RuntimeException('Unable to remove unpublished Custom CSS backup.');
                    }
                }
            } catch (Throwable $recovery) {
                throw new RuntimeException(
                    'Custom CSS recovery failed; the backup was retained. ' . $recovery->getMessage(),
                    previous: $exception,
                );
            }

            throw $exception;
        } finally {
            $this->journal = null;
        }

        if ($published) {
            DB::afterCommit(function (): void {
                try {
                    $this->recover();
                } catch (Throwable $exception) {
                    // Do not retry an already committed import because backup cleanup failed.
                    report($exception);
                }
            });
        }

        return $result;
    }

    /** Replace user.css inside an active promotion transaction. */
    public function publish(string $css): void
    {
        if ($this->journal === null) {
            throw new RuntimeException('Custom CSS promotion must run inside a promotion transaction.');
        }
        if ($this->journal['published']) {
            throw new RuntimeException('Custom CSS has already been published in this transaction.');
        }

        $this->withMutationLock(function () use ($css): void {
            $this->stylesheet->ensureExists();
            $local = Storage::disk('local');
            if (! $local->put($this->journal['backup'], $this->stylesheet->contents())) {
                throw new RuntimeException('Unable to back up current Custom CSS.');
            }

            $this->journal['published'] = true;
            $this->journal['published_sha256'] = hash('sha256', $css);
            $this->persistJournal();
            $this->stylesheet->save($css);
        });
    }

    /** Recover an interrupted import publication before subsequent transfer mutation/cleanup. */
    public function recover(?bool $committed = null): void
    {
        if ($this->journal !== null) {
            return;
        }

        $this->withMutationLock(fn() => $this->recoverUnlocked($committed));
    }

    private function recoverUnlocked(?bool $committed = null): void
    {
        if ($this->journal !== null) {
            return;
        }

        $journal = $this->readJournal();
        if ($journal === null) {
            return;
        }

        $committed ??= $this->isCommitted($journal);
        if (! $committed && hash('sha256', $this->stylesheet->contents()) === $journal['published_sha256']) {
            $backup = Storage::disk('local')->get($journal['backup']);
            $this->stylesheet->save($backup);
        }

        $this->finishRecovery($journal);
    }

    private function withMutationLock(Closure $callback): mixed
    {
        return Cache::lock(self::MUTATION_LOCK, self::MUTATION_LOCK_SECONDS)
            ->block(self::MUTATION_LOCK_WAIT_SECONDS, $callback);
    }

    private function readJournal(): ?array
    {
        $local = Storage::disk('local');
        if (! $local->exists(self::JOURNAL)) {
            return null;
        }

        $journal = $local->json(self::JOURNAL, JSON_THROW_ON_ERROR);
        $token = $journal['token'] ?? null;
        if (
            ! is_array($journal)
            || ! is_string($token)
            || ! preg_match('/\A[a-f0-9]{32}\z/', $token)
            || ! is_int($journal['item_id'] ?? null)
            || ($journal['backup'] ?? null) !== 'UserStylesheetPromotions/' . $token . '/user.css'
            || ($journal['published'] ?? null) !== true
            || ! is_string($journal['published_sha256'] ?? null)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $journal['published_sha256'])
        ) {
            throw new RuntimeException('Invalid Custom CSS recovery journal. Retain the backup for manual recovery.');
        }
        if (! $local->exists($journal['backup'])) {
            throw new RuntimeException('Custom CSS recovery backup is missing. Retain the journal for manual recovery.');
        }

        return $journal;
    }

    private function isCommitted(array $journal): bool
    {
        $owner = LibraryImportItem::find($journal['item_id']);

        return $owner && ($owner->result[self::RESULT_KEY] ?? null) === $journal['token'];
    }

    private function persistJournal(): void
    {
        $local = Storage::disk('local');
        if (
            ! $local->put(self::JOURNAL . '.next', json_encode($this->journal, JSON_THROW_ON_ERROR))
            || ! rename($local->path(self::JOURNAL . '.next'), $local->path(self::JOURNAL))
        ) {
            throw new RuntimeException('Unable to persist Custom CSS recovery journal.');
        }
    }

    private function finishRecovery(array $journal): void
    {
        $local = Storage::disk('local');

        // Delete the pointer first so interrupted backup cleanup cannot replay a finished undo.
        if (! $local->delete(self::JOURNAL)) {
            throw new RuntimeException('Unable to finish Custom CSS recovery.');
        }
        if (! $local->deleteDirectory('UserStylesheetPromotions/' . $journal['token'])) {
            report(new RuntimeException('Unused Custom CSS backup remains in private storage.'));
        }
    }
}
