<?php

namespace App\Support;

use App\Models\LibraryImportItem;
use App\Models\Product;
use App\Models\RefetchWorkResult;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ProductImagePromotion
{
    private const JOURNAL = 'ImagePromotions/active.json';

    private const IMAGE_MUTATION_COMMIT_OPTION = '_image_mutation_promotion_commit';

    /**
     * @var array{
     *     token: string,
     *     owner: 'mutation'|'import'|'refetch',
     *     id: int|string|null,
     *     entries: list<array{destination: string, backup: string|null}>,
     *     products: list<string>
     * }|null
     */
    private ?array $journal = null;

    public function __construct(private readonly ProductImageCleanupService $cleanup) {}

    /** Database commit state and image references commit together; files have a durable undo journal. */
    public function transaction(Closure $callback, LibraryImportItem|RefetchWorkResult $owner): mixed
    {
        return $this->runTransaction($callback, $owner);
    }

    /** Direct image mutations use the same durable journal with a transactional Options commit marker. */
    public function imageMutationTransaction(Closure $callback): mixed
    {
        return $this->runTransaction($callback);
    }

    private function runTransaction(Closure $callback, LibraryImportItem|RefetchWorkResult|null $owner = null): mixed
    {
        if ($this->journal !== null) {
            return $callback();
        }

        $directMutation = $owner === null;

        // Must run under the shared library lifecycle lock. This also finishes any interrupted Refetch, Transfer, or earlier direct image publication before another mutation starts.
        $this->recover();
        if ($directMutation) {
            // A completed recovery can be interrupted after deleting the journal but before deleting its commit marker. With no active journal, that marker is stale.
            DB::table('options')->where('key', self::IMAGE_MUTATION_COMMIT_OPTION)->delete();
        }

        $token = bin2hex(random_bytes(16));
        $this->journal = [
            'token' => $token,
            'owner' => $directMutation ? 'mutation' : ($owner instanceof LibraryImportItem ? 'import' : 'refetch'),
            'id' => $owner?->getKey(),
            'entries' => [],
            'products' => [],
        ];

        try {
            $result = DB::transaction(function () use ($callback, $owner, $token, $directMutation): mixed {
                $result = $callback();

                if ($this->journal['products'] !== []) {
                    if ($directMutation) {
                        $now = now();
                        DB::table('options')->updateOrInsert(
                            ['key' => self::IMAGE_MUTATION_COMMIT_OPTION],
                            ['value' => $token, 'created_at' => $now, 'updated_at' => $now],
                        );
                    } else {
                        $column = $owner instanceof LibraryImportItem ? 'result' : 'decisions';
                        $owner->refresh();
                        $owner->forceFill([$column => [...($owner->$column ?? []), '_image_promotion' => $token]])->save();
                    }
                }

                return $result;
            });
        } catch (Throwable $exception) {
            $this->journal = null;
            try {
                $local = Storage::disk('local');
                if ($local->exists(self::JOURNAL)) {
                    $this->recover(false);
                } else {
                    $local->delete(self::JOURNAL . '.next');
                    $backupDirectory = 'ImagePromotions/' . $token;
                    if ($local->exists($backupDirectory) && ! $local->deleteDirectory($backupDirectory)) {
                        throw new RuntimeException('Unable to remove unpublished image backups.');
                    }
                }
            } catch (Throwable $recovery) {
                throw new RuntimeException('Image recovery failed; backups were retained. ' . $recovery->getMessage(), previous: $exception);
            }
            throw $exception;
        } finally {
            $this->journal = null;
        }

        DB::afterCommit(function (): void {
            try {
                $this->recover();
            } catch (Throwable $exception) {
                // Do not retry an already committed library change because cleanup failed.
                report($exception);
            }
        });

        return $result;
    }

    /** Called under the shared lifecycle lock before any subsequent mutation or cleanup. */
    public function recover(?bool $committed = null): void
    {
        if ($this->journal !== null) {
            return;
        }

        $journal = $this->readJournal();
        if ($journal === null) {
            return;
        }

        $committed ??= $this->isCommitted($journal);
        if (! $committed) {
            $this->restoreImages($journal);
        } else {
            foreach ($journal['products'] as $id) {
                if ($product = Product::find($id)) {
                    $this->cleanup->cleanup($product);
                }
            }
        }

        $this->finishRecovery($journal);
    }

    private function readJournal(): ?array
    {
        $local = Storage::disk('local');

        if (! $local->exists(self::JOURNAL)) {
            return null;
        }

        $journal = $local->json(self::JOURNAL, JSON_THROW_ON_ERROR);

        if (
            ! is_array($journal)
            || ! preg_match('/\A[a-f0-9]{32}\z/', $journal['token'] ?? '')
            || ! isset($journal['entries'], $journal['products'])
        ) {
            throw new RuntimeException('Invalid image recovery journal. Retain backups for manual recovery.');
        }

        return $journal;
    }

    private function isCommitted(array $journal): bool
    {
        if (($journal['owner'] ?? null) === 'mutation') {
            return DB::table('options')
                ->where('key', self::IMAGE_MUTATION_COMMIT_OPTION)
                ->where('value', $journal['token'])
                ->exists();
        }

        $owner = match ($journal['owner'] ?? null) {
            'import' => LibraryImportItem::find($journal['id']),
            'refetch' => RefetchWorkResult::find($journal['id']),
            default => throw new RuntimeException('Invalid image recovery owner.'),
        };
        $column = $journal['owner'] === 'import' ? 'result' : 'decisions';

        return $owner && ($owner->$column['_image_promotion'] ?? null) === $journal['token'];
    }

    private function restoreImages(array $journal): void
    {
        $disk = Storage::disk('public');
        foreach (array_reverse($journal['entries']) as $entry) {
            $this->validateDestination($entry['destination']);
            if ($entry['backup'] === null) {
                if ($disk->exists($entry['destination']) && ! $disk->delete($entry['destination'])) {
                    throw new RuntimeException('Unable to remove an uncommitted image.');
                }
            } else {
                $expected = 'ImagePromotions/' . $journal['token'] . '/' . $entry['destination'];
                if ($entry['backup'] !== $expected) {
                    throw new RuntimeException('Invalid image recovery backup path.');
                }
                $this->publish('local', $entry['backup'], $entry['destination']);
            }
        }
    }

    private function finishRecovery(array $journal): void
    {
        $local = Storage::disk('local');

        // Delete the pointer first: an interrupted backup cleanup cannot replay a finished undo.
        if (! $local->delete(self::JOURNAL)) {
            throw new RuntimeException('Unable to finish image recovery.');
        }
        if (! $local->deleteDirectory('ImagePromotions/' . $journal['token'])) {
            report(new RuntimeException('Unused image backups remain in private storage.'));
        }

        if (($journal['owner'] ?? null) === 'mutation') {
            DB::table('options')
                ->where('key', self::IMAGE_MUTATION_COMMIT_OPTION)
                ->where('value', $journal['token'])
                ->delete();
        }
    }

    /** @param list<array{source: string, destination: string, sha256?: string}> $promotions */
    public function promote(Product $product, array $promotions, array $attributes, string $sourceDisk = 'public'): bool
    {
        return $this->promoteUsing(
            $product,
            $promotions,
            $attributes,
            function (array $promotion) use ($sourceDisk): void {
                $this->publish(
                    $sourceDisk,
                    $promotion['source'],
                    $promotion['destination'],
                    $promotion['sha256'] ?? null,
                );
            },
        );
    }

    /** @param list<array{file: UploadedFile, destination: string}> $promotions */
    public function promoteUploads(Product $product, array $promotions, array $attributes): bool
    {
        return $this->promoteUsing(
            $product,
            $promotions,
            $attributes,
            function (array $promotion): void {
                $this->publishUpload($promotion['file'], $promotion['destination']);
            },
        );
    }

    private function promoteUsing(Product $product, array $promotions, array $attributes, Closure $publish): bool
    {
        if ($this->journal === null) {
            throw new RuntimeException('Image promotion must run inside an image transaction.');
        }

        foreach ($promotions as $promotion) {
            $this->backupDestination($promotion['destination']);
        }

        $this->journal['products'] = array_values(array_unique([...$this->journal['products'], $product->id]));
        $this->persistJournal();

        foreach ($promotions as $promotion) {
            $publish($promotion);
        }

        $product->forceFill($attributes);
        $product->touch();

        return true;
    }

    private function backupDestination(string $destination): void
    {
        $this->validateDestination($destination);
        if (collect($this->journal['entries'])->contains('destination', $destination)) {
            return;
        }

        $disk = Storage::disk('public');
        $backup = null;
        if ($disk->exists($destination)) {
            $backup = 'ImagePromotions/' . $this->journal['token'] . '/' . $destination;
            $stream = $disk->readStream($destination);
            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to back up current image.');
            }
            try {
                if (! Storage::disk('local')->put($backup, $stream)) {
                    throw new RuntimeException('Unable to back up current image.');
                }
            } finally {
                fclose($stream);
            }
        }
        $this->journal['entries'][] = ['destination' => $destination, 'backup' => $backup];
    }

    private function persistJournal(): void
    {
        $local = Storage::disk('local');
        if (
            ! $local->put(self::JOURNAL . '.next', json_encode($this->journal, JSON_THROW_ON_ERROR))
            || ! rename($local->path(self::JOURNAL . '.next'), $local->path(self::JOURNAL))
        ) {
            throw new RuntimeException('Unable to persist image recovery journal.');
        }
    }

    private function validateDestination(string $path): void
    {
        if (! preg_match('/\\AWorks\\/(?:RJ|BJ|VJ)\\d+\\/(cover|sample_[1-9]\\d*)\\.(jpe?g|png|gif|webp|avif|bmp)\\z/', $path)) {
            throw new RuntimeException('Unsafe image replacement path.');
        }
    }

    private function publishUpload(UploadedFile $file, string $destination): void
    {
        $stream = fopen($file->getPathname(), 'rb');
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read uploaded image.');
        }

        $this->publishStream($stream, $destination);
    }

    private function publish(string $sourceDisk, string $source, string $destination, ?string $expectedHash = null): void
    {
        $stream = Storage::disk($sourceDisk)->readStream($source);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read staged image.');
        }

        $this->publishStream($stream, $destination, $expectedHash);
    }

    /** @param resource $stream */
    private function publishStream($stream, string $destination, ?string $expectedHash = null): void
    {
        $disk = Storage::disk('public');
        $temporary = $destination . '.incoming';
        $published = false;
        try {
            try {
                if (! $disk->put($temporary, $stream)) {
                    throw new RuntimeException('Failed to promote staged image.');
                }
            } finally {
                fclose($stream);
            }

            if ($expectedHash !== null && hash_file('sha256', $disk->path($temporary)) !== $expectedHash) {
                throw new RuntimeException('Staged image changed during promotion.');
            }

            // PHP cannot replace an existing file with rename() on Windows. The journal already contains a backup at this point, so deleting the destination remains recoverable.
            if ($disk->exists($destination) && ! $disk->delete($destination)) {
                throw new RuntimeException('Failed to replace current image.');
            }
            if (! rename($disk->path($temporary), $disk->path($destination))) {
                throw new RuntimeException('Failed to publish staged image.');
            }

            $published = true;

            clearstatcache(true, $disk->path($destination));
        } finally {
            if (! $published) {
                $disk->delete($temporary);
            }
        }
    }
}
