<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPE_AUTO_GENERATED_JAPANESE = 'auto_generated_japanese';

    private const TYPE_AUTO_GENERATED_ENGLISH = 'auto_generated_english';

    private const TYPE_CUSTOM = 'custom';

    private const SOURCE_FETCHED = 'fetched';

    private const LANGUAGE_JAPANESE = 'jp';

    private const LANGUAGE_ENGLISH = 'en';

    public function up(): void
    {
        if (! Schema::hasTable('genre_product_languages')) {
            Schema::create('genre_product_languages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('genre_product_id')->constrained('genre_product')->cascadeOnDelete();
                $table->string('language', 32);
                $table->timestamps();

                $table->unique(['genre_product_id', 'language'], 'genre_product_language_unique');
                $table->index('language');
            });
        }

        if (Schema::hasColumn('genres', 'type')) {
            $this->backfillLanguageRows();
        }

        Schema::table('genres', function (Blueprint $table): void {
            if (Schema::hasColumn('genres', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('genres', 'language')) {
                $table->dropColumn('language');
            }
        });
    }

    public function down(): void
    {
        Schema::table('genres', function (Blueprint $table): void {
            if (! Schema::hasColumn('genres', 'type')) {
                $table->string('type', 255)->nullable()->after('order');
            }

            if (! Schema::hasColumn('genres', 'language')) {
                $table->string('language', 255)->nullable()->after('type');
            }
        });

        $this->restoreGenreMetadata();

        Schema::dropIfExists('genre_product_languages');
    }

    private function backfillLanguageRows(): void
    {
        $now = now();

        DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->where('genre_product.source', self::SOURCE_FETCHED)
            ->whereIn('genres.type', [
                self::TYPE_AUTO_GENERATED_JAPANESE,
                self::TYPE_AUTO_GENERATED_ENGLISH,
            ])
            ->orderBy('genre_product.id')
            ->select([
                'genre_product.id',
                'genres.type',
            ])
            ->chunkById(500, function ($rows) use ($now): void {
                DB::table('genre_product_languages')->insertOrIgnore(
                    $rows
                        ->map(fn (object $row): array => [
                            'genre_product_id' => $row->id,
                            'language' => $row->type === self::TYPE_AUTO_GENERATED_JAPANESE
                                ? self::LANGUAGE_JAPANESE
                                : self::LANGUAGE_ENGLISH,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all()
                );
            }, 'genre_product.id', 'id');
    }

    private function restoreGenreMetadata(): void
    {
        DB::table('genres')->update([
            'type' => self::TYPE_CUSTOM,
            'language' => self::LANGUAGE_ENGLISH,
        ]);

        DB::table('genre_product_languages')
            ->join('genre_product', 'genre_product.id', '=', 'genre_product_languages.genre_product_id')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->where('genre_product_languages.language', self::LANGUAGE_ENGLISH)
            ->update([
                'genres.type' => self::TYPE_AUTO_GENERATED_ENGLISH,
                'genres.language' => self::LANGUAGE_ENGLISH,
            ]);

        DB::table('genre_product_languages')
            ->join('genre_product', 'genre_product.id', '=', 'genre_product_languages.genre_product_id')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->where('genre_product_languages.language', self::LANGUAGE_JAPANESE)
            ->where('genres.type', self::TYPE_CUSTOM)
            ->update([
                'genres.type' => self::TYPE_AUTO_GENERATED_JAPANESE,
                'genres.language' => self::LANGUAGE_JAPANESE,
            ]);
    }
};
