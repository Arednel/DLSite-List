<?php

use App\Support\DLSite\DLSiteWorkData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('site_id')->nullable()->after('id');
        });

        // Use the stored scraper response; do not infer the section from the product code or age rating.
        DB::table('products')->select('id')->orderBy('id')->chunk(200, function ($products): void {
            foreach ($products as $product) {
                $path = "Works/{$product->id}.json";

                if (! Storage::disk('local')->exists($path)) {
                    continue;
                }

                try {
                    $payload = json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
                    $work = DLSiteWorkData::fromArray(is_array($payload) ? $payload : [], $product->id);
                } catch (Throwable $exception) {
                    Log::warning('Unable to backfill DLsite section from scraper JSON.', [
                        'product_id' => $product->id,
                        'path' => $path,
                        'error' => $exception->getMessage(),
                    ]);

                    continue;
                }

                if ($work->siteId !== null) {
                    DB::table('products')->where('id', $product->id)->update(['site_id' => $work->siteId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('site_id');
        });
    }
};
