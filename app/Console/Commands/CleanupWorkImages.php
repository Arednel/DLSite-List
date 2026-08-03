<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\ProductImageCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupWorkImages extends Command
{
    protected $signature = 'works:cleanup-images';

    protected $description = 'Cleanup unreferenced cover and sample images from existing RJ work folders';

    public function handle(ProductImageCleanupService $cleanup): int
    {
        $processed = 0;
        $removed = 0;
        $skipped = 0;

        foreach (Storage::disk('public')->directories('Works') as $directory) {
            $productId = basename($directory);

            if (Product::rjNumberFromId($productId) === null) {
                continue;
            }

            $product = Product::find($productId);

            if (! $product) {
                $skipped++;

                continue;
            }

            $removed += $cleanup->cleanup($product);
            $processed++;
        }

        $this->info(
            "Cleanup complete: processed {$processed} RJ work folder(s); "
                . "removed {$removed} unreferenced image(s)."
        );

        if ($skipped > 0) {
            $this->info(
                "Skipped {$skipped} RJ work folder(s) without a matching product."
            );
        }

        return self::SUCCESS;
    }
}
