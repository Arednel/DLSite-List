<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_localizations', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('title');
        });

        DB::table('products')
            ->select(['id', 'description_english'])
            ->whereNotNull('description_english')
            ->orderBy('id')
            ->chunk(500, function ($products): void {
                $now = now();
                $rows = [];

                foreach ($products as $product) {
                    if (blank($product->description_english)) {
                        continue;
                    }

                    $rows[] = [
                        'product_id' => $product->id,
                        'locale' => 'en',
                        'description' => $product->description_english,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('product_localizations')->upsert(
                        $rows,
                        ['product_id', 'locale'],
                        ['description', 'updated_at'],
                    );
                }
            });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('description_english');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->text('description_english')->nullable();
        });

        DB::table('product_localizations')
            ->where('locale', 'en')
            ->orderBy('product_id')
            ->chunk(500, function ($localizations): void {
                foreach ($localizations as $localization) {
                    DB::table('products')
                        ->where('id', $localization->product_id)
                        ->update([
                            'description_english' => $localization->description,
                        ]);
                }
            });

        DB::table('product_localizations')
            ->select(['id', 'title'])
            ->chunkById(500, function ($localizations): void {
                $ids = [];

                foreach ($localizations as $localization) {
                    if (blank($localization->title)) {
                        $ids[] = $localization->id;
                    }
                }

                if ($ids !== []) {
                    DB::table('product_localizations')
                        ->whereIn('id', $ids)
                        ->delete();
                }
            });

        Schema::table('product_localizations', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
