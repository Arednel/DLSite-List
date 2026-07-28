<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const IMAGE_EXTENSION_PATTERN = '(?:avif|bmp|gif|jpe?g|png|webp)';

    public function up(): void
    {
        DB::table('products')
            ->select(['id', 'work_image', 'sample_images'])
            ->eachById(function (object $product): void {
                $productId = (string) $product->id;

                $sampleImages = $this->sampleImages($product->sample_images);
                $normalizedSamples = $this->normalizeSampleStoragePaths($productId, $sampleImages ?? []);
                $normalizedCover = $this->normalizeCoverStoragePath($productId, $product->work_image);

                if (
                    $normalizedCover === $product->work_image
                    && $sampleImages !== null
                    && $normalizedSamples === $sampleImages
                ) {
                    return;
                }

                DB::table('products')
                    ->where('id', $productId)
                    ->update([
                        'work_image' => $normalizedCover,
                        'sample_images' => json_encode($normalizedSamples, JSON_THROW_ON_ERROR),
                    ]);
            }, count: 200);
    }

    public function down(): void
    {
        // Historical upstream URLs cannot be reconstructed from local potential paths.
    }

    private function normalizeCoverStoragePath(string $productId, mixed $path): string
    {
        return $this->sameWorkImagePath($productId, $path, 'cover')
            ?? $this->storagePath($productId, 'cover.jpg');
    }

    /**
     * @param  list<mixed>  $paths
     * @return list<string>
     */
    private function normalizeSampleStoragePaths(string $productId, array $paths): array
    {
        $normalized = [];

        foreach (array_values($paths) as $index => $path) {
            $position = $index + 1;
            $basename = "sample_{$position}";
            $normalized[] = $this->sameWorkImagePath($productId, $path, $basename)
                ?? $this->storagePath($productId, "{$basename}.jpg");
        }

        return $normalized;
    }

    private function sameWorkImagePath(string $productId, mixed $path, string $basename): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);
        $pattern = sprintf(
            '/\A\/?storage\/Works\/%s\/%s\.(?<extension>%s)\z/i',
            preg_quote($productId, '/'),
            preg_quote($basename, '/'),
            self::IMAGE_EXTENSION_PATTERN,
        );

        if (! preg_match($pattern, $path, $matches)) {
            return null;
        }

        return $this->storagePath(
            $productId,
            "{$basename}." . strtolower($matches['extension']),
        );
    }

    private function storagePath(string $productId, string $filename): string
    {
        return "storage/Works/{$productId}/{$filename}";
    }

    /**
     * @return list<mixed>|null
     */
    private function sampleImages(mixed $value): ?array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : null;
    }
};
