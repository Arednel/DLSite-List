<?php

use App\Support\DLSite\DLSiteWorkData;
use App\Support\ProductContributorSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $sync = app(ProductContributorSync::class);

        DB::table('products')
            ->select(['id', 'series'])
            ->orderBy('id')
            ->chunk(200, function ($products) use ($sync): void {
                foreach ($products as $product) {
                    $this->backfillProduct($product, $sync);
                }
            });
    }

    public function down(): void
    {
        // This migration imports historical scraper metadata and is not safely reversible.
    }

    private function backfillProduct(object $product, ProductContributorSync $sync): void
    {
        $path = "Works/{$product->id}.json";

        if (! Storage::disk('local')->exists($path)) {
            return;
        }

        try {
            $payload = json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
            $workData = DLSiteWorkData::fromArray(is_array($payload) ? $payload : [], $product->id);
        } catch (Throwable $exception) {
            Log::warning('Unable to backfill product metadata from scraper JSON.', [
                'product_id' => $product->id,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        DB::table('products')
            ->where('id', $product->id)
            ->update([
                'maker_id' => $workData->makerId,
                'circle' => $workData->circle,
                'description' => $workData->description,
                'description_english' => $workData->englishDescription,
            ]);

        $eloquentProduct = \App\Models\Product::query()->find($product->id);

        if ($eloquentProduct) {
            $sync->sync($eloquentProduct, $workData->contributorsByRole, $workData->makerId);
        }
    }
};
