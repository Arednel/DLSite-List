<?php

namespace App\Support;

use App\Models\LibraryImportItem;
use App\Models\Product;
use App\Models\RefetchWorkResult;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ProductImagePromotion
{
    private const JOURNAL = 'ImagePromotions/active.json';

    private ?array $journal = null;

    public function __construct(private readonly ProductImageCleanupService $cleanup) {}

    /** The owner result and image references commit together; files have a durable undo journal. */
    public function transaction(Closure $callback, ?Model $owner = null): mixed
    {
        if ($this->journal !== null) {
            return $callback();
        }
        $this->recover();
        $token = bin2hex(random_bytes(16));
        $this->journal = [
            'token' => $token,
            'owner' => $owner instanceof LibraryImportItem ? 'import' : 'refetch',
            'id' => $owner?->getKey(),
            'entries' => [],
            'products' => [],
        ];
        try {
            $result = DB::transaction(function () use ($callback, $owner, $token): mixed {
                $result = $callback();
                if ($this->journal['products'] !== []) {
                    if (! $owner instanceof LibraryImportItem && ! $owner instanceof RefetchWorkResult) {
                        throw new RuntimeException('Image replacement requires a persisted review result.');
                    }
                    $column = $owner instanceof LibraryImportItem ? 'result' : 'decisions';
                    $owner->refresh();
                    $owner->forceFill([$column => [...($owner->$column ?? []), '_image_promotion' => $token]])->save();
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
        $local = Storage::disk('local');
        if ($this->journal !== null || ! $local->exists(self::JOURNAL)) {
            return;
        }
        $journal = json_decode($local->get(self::JOURNAL), true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($journal) || ! preg_match('/\\A[a-f0-9]{32}\\z/', $journal['token'] ?? '') || ! isset($journal['entries'], $journal['products'])) {
            throw new RuntimeException('Invalid image recovery journal. Retain backups for manual recovery.');
        }
        if ($committed === null) {
            $owner = match ($journal['owner']) {
                'import' => LibraryImportItem::find($journal['id']),
                'refetch' => RefetchWorkResult::find($journal['id']),
                default => throw new RuntimeException('Invalid image recovery owner.'),
            };
            $column = $journal['owner'] === 'import' ? 'result' : 'decisions';
            $committed = $owner && ($owner->$column['_image_promotion'] ?? null) === $journal['token'];
        }
        if (! $committed) {
            foreach (array_reverse($journal['entries']) as $entry) {
                $this->validateDestination($entry['destination']);
                if ($entry['backup'] === null) {
                    if (Storage::disk('public')->exists($entry['destination']) && ! Storage::disk('public')->delete($entry['destination'])) {
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
        } else {
            foreach ($journal['products'] as $id) {
                if ($product = Product::find($id)) {
                    $this->cleanup->cleanup($product);
                }
            }
        }
        // Delete the pointer first: an interrupted backup cleanup cannot replay a finished undo.
        if (! $local->delete(self::JOURNAL)) {
            throw new RuntimeException('Unable to finish image recovery.');
        }
        if (! $local->deleteDirectory('ImagePromotions/' . $journal['token'])) {
            report(new RuntimeException('Unused image backups remain in private storage.'));
        }
    }

    /** @param list<array{source: string, destination: string, sha256?: string}> $promotions */
    public function promote(Product $product, array $promotions, array $attributes, string $sourceDisk = 'public'): bool
    {
        if ($this->journal === null) {
            throw new RuntimeException('Image promotion must run inside a review transaction.');
        }
        $disk = Storage::disk('public');
        foreach ($promotions as $promotion) {
            $destination = $promotion['destination'];
            $this->validateDestination($destination);
            if (collect($this->journal['entries'])->contains('destination', $destination)) {
                continue;
            }
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
        $this->journal['products'] = array_values(array_unique([...$this->journal['products'], $product->id]));
        $local = Storage::disk('local');
        if (
            ! $local->put(self::JOURNAL . '.next', json_encode($this->journal, JSON_THROW_ON_ERROR))
            || ! rename($local->path(self::JOURNAL . '.next'), $local->path(self::JOURNAL))
        ) {
            throw new RuntimeException('Unable to persist image recovery journal.');
        }
        foreach ($promotions as $promotion) {
            $this->publish($sourceDisk, $promotion['source'], $promotion['destination'], $promotion['sha256'] ?? null);
        }
        $product->forceFill($attributes);
        $product->touch();

        return true;
    }

    private function validateDestination(string $path): void
    {
        if (! preg_match('/\\AWorks\\/RJ\\d+\\/(cover|sample_[1-9]\\d*)\\.(jpe?g|png|gif|webp|avif|bmp)\\z/', $path)) {
            throw new RuntimeException('Unsafe image replacement path.');
        }
    }

    private function publish(string $sourceDisk, string $source, string $destination, ?string $expectedHash = null): void
    {
        $stream = Storage::disk($sourceDisk)->readStream($source);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read staged image.');
        }
        $disk = Storage::disk('public');
        $temporary = $destination . '.incoming';
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
        if (! rename($disk->path($temporary), $disk->path($destination))) {
            throw new RuntimeException('Failed to publish staged image.');
        }
        clearstatcache(true, $disk->path($destination));
    }
}
