<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_localizations', function (Blueprint $table): void {
            $table->id();
            $table->string('product_id');
            $table->string('locale', 16);
            $table->string('title', 1000)->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['product_id', 'locale']);
        });

        DB::table('products')
            ->select(['id', 'work_name_english'])
            ->whereNotNull('work_name_english')
            ->orderBy('id')
            ->chunk(500, function ($products): void {
                $now = now();
                $rows = [];

                foreach ($products as $product) {
                    if (blank($product->work_name_english)) {
                        continue;
                    }

                    $rows[] = [
                        'product_id' => $product->id,
                        'locale' => 'en',
                        'title' => $product->work_name_english,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('product_localizations')->insert($rows);
                }
            });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('work_name_english');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('work_name_english', 1000)->nullable();
        });

        DB::table('product_localizations')
            ->where('locale', 'en')
            ->orderBy('product_id')
            ->chunk(500, function ($localizations): void {
                foreach ($localizations as $localization) {
                    DB::table('products')->where('id', $localization->product_id)->update([
                        'work_name_english' => $localization->title,
                    ]);
                }
            });

        Schema::dropIfExists('product_localizations');
    }
};
