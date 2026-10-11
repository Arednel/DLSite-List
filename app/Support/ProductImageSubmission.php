<?php

namespace App\Support;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class ProductImageSubmission
{
    public function __construct(
        private readonly LibraryMutationLock $mutationLock,
        private readonly ProductImagePromotion $promotion,
    ) {}

    public function create(
        Request $request,
        string $productId,
        bool $allowCover,
        bool $allowSamples,
        Closure $callback,
    ): mixed {
        $cover = $allowCover ? $request->file('work_image') : null;
        $samples = $allowSamples ? $this->orderedNewSamples($request) : [];

        $promotions = [];
        $coverPath = null;
        if ($cover instanceof UploadedFile) {
            $coverPath = $this->addUpload($productId, 'cover', $cover, $promotions);
        }

        $samplePaths = [];
        foreach ($samples as $index => $file) {
            $samplePaths[] = $this->addUpload($productId, 'sample_' . ($index + 1), $file, $promotions);
        }

        $images = [
            'work_image' => $coverPath,
            'sample_images' => $samplePaths,
        ];

        if ($promotions === []) {
            return DB::transaction(fn(): mixed => $callback($images));
        }

        $lockErrorField = $cover instanceof UploadedFile ? 'work_image' : 'sample_images';

        return $this->withImageMutationTransaction(function () use ($callback, $images, $promotions, $productId): mixed {
            // imageMutationTransaction() recovers any interrupted promotion before this callback.
            // Only reject files that still exist after recovery as genuine destination conflicts.
            foreach ($promotions as $item) {
                if (Storage::disk('public')->exists($item['destination'])) {
                    $field = str_contains($item['destination'], '/cover.') ? 'work_image' : 'sample_images';

                    throw ValidationException::withMessages([
                        $field => __('An image already exists at the destination. Remove the leftover file and try again.'),
                    ]);
                }
            }

            $product = $callback($images);
            if (! $product instanceof Product || (string) $product->getKey() !== $productId) {
                throw new \RuntimeException('Image create callback did not return the expected product.');
            }

            $this->promotion->promoteUploads($product, $promotions, $images);

            return $product;
        }, $lockErrorField);
    }

    public function update(
        Request $request,
        Product $product,
        bool $allowCover,
        bool $allowSamples,
        Closure $callback,
    ): mixed {
        $currentCover = $product->work_image;
        $currentSamples = array_values($product->sample_images ?? []);
        $promotions = [];

        $cover = $currentCover;
        $coverChanged = false;
        if ($allowCover) {
            $uploadedCover = $request->file('work_image');
            if ($uploadedCover instanceof UploadedFile) {
                $cover = $this->addUpload($product->id, 'cover', $uploadedCover, $promotions);
                $coverChanged = true;
            } elseif ($request->boolean('remove_work_image') && $currentCover !== null) {
                $cover = null;
                $coverChanged = true;
            }
        }

        $samples = $currentSamples;
        if ($allowSamples && ($request->boolean('sample_images_submitted')
            || $request->has('sample_image_order')
            || $request->hasFile('sample_images'))) {
            [$samples, $samplePromotions] = $this->orderedSamplesForUpdate($request, $product, $currentSamples);
            $promotions = [...$promotions, ...$samplePromotions];
        }

        $images = [
            'work_image' => $cover,
            'sample_images' => $samples,
        ];

        if (! $coverChanged && $this->sameSampleFiles($currentSamples, $samples)) {
            return DB::transaction(fn(): mixed => $callback($images));
        }

        $lockErrorField = $coverChanged ? 'work_image' : 'sample_images';

        return $this->withImageMutationTransaction(function () use ($product, $promotions, $images, $callback): mixed {
            $this->promotion->promoteUploads($product, $promotions, $images);

            return $callback($images);
        }, $lockErrorField);
    }

    /** @return list<UploadedFile> */
    private function orderedNewSamples(Request $request): array
    {
        $uploads = $this->uploadedSamples($request);
        $ordered = [];
        $used = [];

        foreach ($request->array('sample_image_order') as $item) {
            if (! is_string($item) || ! str_starts_with($item, 'new:')) {
                continue;
            }

            $key = substr($item, 4);
            if (isset($uploads[$key]) && ! isset($used[$key])) {
                $ordered[] = $uploads[$key];
                $used[$key] = true;
            }
        }

        foreach ($uploads as $key => $file) {
            if (! isset($used[$key])) {
                $ordered[] = $file;
            }
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $currentSamples
     * @return array{0: list<string>, 1: list<array{file: UploadedFile, destination: string}>}
     */
    private function orderedSamplesForUpdate(Request $request, Product $product, array $currentSamples): array
    {
        $uploads = $this->uploadedSamples($request);
        $order = $request->has('sample_image_order')
            ? $request->array('sample_image_order')
            : ($request->boolean('sample_images_submitted')
                ? []
                : array_map(fn(string $path): string => "existing:{$path}", $currentSamples));
        $nextNumber = $this->nextSampleNumber($currentSamples);
        $samples = [];
        $promotions = [];
        $usedExisting = [];
        $usedUploads = [];

        foreach ($order as $item) {
            if (! is_string($item)) {
                $this->invalidSampleOrder();
            }

            if (str_starts_with($item, 'existing:')) {
                $path = substr($item, 9);
                if (! in_array($path, $currentSamples, true) || isset($usedExisting[$path])) {
                    $this->invalidSampleOrder();
                }

                $samples[] = $path;
                $usedExisting[$path] = true;
                continue;
            }

            if (str_starts_with($item, 'new:')) {
                $key = substr($item, 4);
                if (! isset($uploads[$key]) || isset($usedUploads[$key])) {
                    $this->invalidSampleOrder();
                }

                $samples[] = $this->addUpload($product->id, "sample_{$nextNumber}", $uploads[$key], $promotions);
                $nextNumber++;
                $usedUploads[$key] = true;
                continue;
            }

            $this->invalidSampleOrder();
        }

        foreach ($uploads as $key => $file) {
            if (isset($usedUploads[$key])) {
                continue;
            }

            $samples[] = $this->addUpload($product->id, "sample_{$nextNumber}", $file, $promotions);
            $nextNumber++;
        }

        return [$samples, $promotions];
    }

    /** @param list<array{file: UploadedFile, destination: string}> $promotions */
    private function addUpload(string $productId, string $name, UploadedFile $file, array &$promotions): string
    {
        $destination = "Works/{$productId}/{$name}.{$file->extension()}";
        $promotions[] = ['file' => $file, 'destination' => $destination];

        return "storage/{$destination}";
    }

    /**
     * @param list<string> $current
     * @param list<string> $submitted
     */
    private function sameSampleFiles(array $current, array $submitted): bool
    {
        // Reordering alone does not require the image mutation lock.
        sort($current);
        sort($submitted);

        return $current === $submitted;
    }

    /** @return array<int|string, UploadedFile> */
    private function uploadedSamples(Request $request): array
    {
        return collect($request->file('sample_images', []))
            ->whereInstanceOf(UploadedFile::class)
            ->all();
    }

    /** @param list<string> $paths */
    private function nextSampleNumber(array $paths): int
    {
        $max = 0;
        foreach ($paths as $path) {
            if (preg_match('/\bsample_(\d+)\.[^.]+\z/i', $path, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max + 1;
    }

    private function invalidSampleOrder(): never
    {
        throw ValidationException::withMessages([
            'sample_image_order' => __('Sample image order is no longer valid. Reload the form and try again.'),
        ]);
    }

    private function withImageMutationTransaction(Closure $callback, string $errorField): mixed
    {
        try {
            return $this->mutationLock->run(fn(): mixed => $this->promotion->imageMutationTransaction($callback));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                $errorField => __('Images cannot be saved while another library operation is in progress. Try again.'),
            ]);
        }
    }
}
