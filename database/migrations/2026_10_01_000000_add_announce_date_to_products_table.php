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
            $table->dateTime('announce_date')->nullable()->after('product_format');
        });

        // Existing scraper JSON files already contain this metadata.
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
                    Log::warning('Unable to backfill announcement date from scraper JSON.', [
                        'product_id' => $product->id,
                        'path' => $path,
                        'error' => $exception->getMessage(),
                    ]);

                    continue;
                }

                // Database update failures should abort the migration rather than be treated like an unreadable/corrupt historical scraper JSON file.
                if ($work->announceDate !== null) {
                    DB::table('products')->where('id', $product->id)->update(['announce_date' => $work->announceDate]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('announce_date');
        });
    }
};
