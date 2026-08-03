<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ProductImageCleanupService
{
    private const IMAGE_FILENAME_PATTERN =
    '/\A(?:cover|sample_[1-9]\d*)\.(?:avif|bmp|gif|jpe?g|png|webp)\z/i';

    public function cleanup(Product $product): int
    {
        $productId = (string) $product->getKey();
        $directory = "Works/{$productId}";
        $disk = Storage::disk('public');
        $savedPaths = [
            $product->work_image,
            ...($product->sample_images ?? []),
        ];
        $leftovers = collect($disk->files($directory))
            ->filter(fn(string $path): bool => (
                preg_match(
                    self::IMAGE_FILENAME_PATTERN,
                    basename($path),
                ) === 1
                && ! in_array(
                    "storage/{$path}",
                    $savedPaths,
                    true,
                )
            ))
            ->all();

        if ($leftovers !== [] && ! $disk->delete($leftovers)) {
            throw new RuntimeException(
                "Failed to remove unreferenced images for {$productId}.",
            );
        }

        return count($leftovers);
    }
}
