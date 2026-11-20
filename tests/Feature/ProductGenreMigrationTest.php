<?php

namespace Tests\Feature;

use App\Enums\DlsiteLanguage;
use App\Models\Genre;
use App\Models\Product;
use App\Support\ProductGenreSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductGenreMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_schema_drops_only_the_unused_global_tag_order(): void
    {
        $this->assertFalse(Schema::hasColumn('genres', 'order'));
        $this->assertTrue(Schema::hasColumn('genre_groups', 'order'));
        $this->assertTrue(Schema::hasColumn('genre_group_genre', 'order'));
    }

    public function test_legacy_genre_json_migration_backfills_language_rows_and_drops_old_metadata(): void
    {
        $now = now();
        Schema::dropIfExists('genre_product_languages');
        $this->restoreLegacyGenreColumns();
        $this->restoreGenreMetadataColumns();

        $sharedGenreId = DB::table('genres')->insertGetId([
            'title' => 'Shared Genre',
            'title_key' => Genre::titleKey('Shared Genre'),
            'description' => null,
            'order' => null,
            'type' => Genre::TYPE_AUTO_GENERATED_ENGLISH,
            'language' => DlsiteLanguage::English->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $product = Product::factory()->create();
        $this->setLegacyGenres($product, ['Japanese Only'], ['Shared Genre'], ['Shared Genre', 'Custom Only']);

        $this->runGenreIdMigration();
        $this->runLanguageBucketMigration();
        $this->runLanguageCodeNormalizationMigration();

        $this->assertFalse(Schema::hasColumn('products', 'genre'));
        $this->assertFalse(Schema::hasColumn('products', 'genre_english'));
        $this->assertFalse(Schema::hasColumn('products', 'genre_custom'));
        $this->assertFalse(Schema::hasColumn('genres', 'type'));
        $this->assertFalse(Schema::hasColumn('genres', 'language'));
        $this->assertTrue(Schema::hasTable('genre_product_languages'));

        $japaneseGenre = DB::table('genres')->where('title', 'Japanese Only')->firstOrFail();
        $customGenre = DB::table('genres')->where('title', 'Custom Only')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$japaneseGenre->id, $sharedGenreId, $customGenre->id],
            DB::table('genre_product')
                ->where('product_id', $product->id)
                ->pluck('genre_id')
                ->all()
        );
        $this->assertEquals(
            [
                $japaneseGenre->id => Genre::PIVOT_SOURCE_FETCHED,
                $sharedGenreId => Genre::PIVOT_SOURCE_FETCHED,
                $customGenre->id => Genre::PIVOT_SOURCE_CUSTOM,
            ],
            DB::table('genre_product')
                ->where('product_id', $product->id)
                ->pluck('source', 'genre_id')
                ->all()
        );
        $this->assertPivotLanguages($product, $japaneseGenre->id, [DlsiteLanguage::Japanese->value]);
        $this->assertPivotLanguages($product, $sharedGenreId, [DlsiteLanguage::English->value]);
        $this->assertPivotLanguages($product, $customGenre->id, []);
        $this->assertSame(
            1,
            DB::table('genres')->where('title', 'Shared Genre')->count()
        );
    }

    public function test_migration_promotes_existing_custom_genre_when_auto_generated_title_matches_it(): void
    {
        $now = now();
        Schema::dropIfExists('genre_product_languages');
        $this->restoreLegacyGenreColumns();
        $this->restoreGenreMetadataColumns();

        $sharedGenreId = DB::table('genres')->insertGetId([
            'title' => 'Shared Genre',
            'title_key' => Genre::titleKey('Shared Genre'),
            'description' => null,
            'order' => null,
            'type' => Genre::TYPE_CUSTOM,
            'language' => DlsiteLanguage::English->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $product = Product::factory()->create();
        $this->setLegacyGenres($product, [], ['Shared Genre'], ['Shared Genre']);

        $this->runGenreIdMigration();
        $this->runLanguageBucketMigration();
        $this->runLanguageCodeNormalizationMigration();

        $sharedGenre = DB::table('genres')->where('id', $sharedGenreId)->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$sharedGenreId],
            DB::table('genre_product')
                ->where('product_id', $product->id)
                ->pluck('genre_id')
                ->all()
        );
        $this->assertSame(
            Genre::PIVOT_SOURCE_FETCHED,
            DB::table('genre_product')
                ->where('product_id', $product->id)
                ->where('genre_id', $sharedGenreId)
                ->value('source')
        );
        $this->assertObjectNotHasProperty('type', $sharedGenre);
        $this->assertObjectNotHasProperty('language', $sharedGenre);
        $this->assertPivotLanguages($product, $sharedGenreId, [DlsiteLanguage::English->value]);
    }

    public function test_language_code_normalization_merges_existing_jp_and_ja_rows(): void
    {
        $product = Product::factory()->create();
        $genre = Genre::query()->create(['title' => 'Japanese Collision']);
        app(ProductGenreSync::class)->sync($product, [
            DlsiteLanguage::Japanese->value => [$genre->getKey()],
        ], []);

        $attachmentId = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('genre_id', $genre->getKey())
            ->value('id');
        DB::table('genre_product_languages')->insert([
            'genre_product_id' => $attachmentId,
            'language' => 'jp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runLanguageCodeNormalizationMigration();

        $this->assertSame(1, DB::table('genre_product_languages')
            ->where('genre_product_id', $attachmentId)
            ->where('language', 'ja')
            ->count());
        $this->assertSame(0, DB::table('genre_product_languages')
            ->where('genre_product_id', $attachmentId)
            ->where('language', 'jp')
            ->count());
    }

    public function test_language_code_normalization_down_merges_existing_ja_and_jp_rows(): void
    {
        $product = Product::factory()->create();
        $genre = Genre::query()->create(['title' => 'Japanese Rollback Collision']);
        app(ProductGenreSync::class)->sync($product, [
            DlsiteLanguage::Japanese->value => [$genre->getKey()],
        ], []);

        $attachmentId = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('genre_id', $genre->getKey())
            ->value('id');
        DB::table('genre_product_languages')->insert([
            'genre_product_id' => $attachmentId,
            'language' => 'jp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runLanguageCodeNormalizationMigrationDown();

        $this->assertSame(1, DB::table('genre_product_languages')
            ->where('genre_product_id', $attachmentId)
            ->where('language', 'jp')
            ->count());
        $this->assertSame(0, DB::table('genre_product_languages')
            ->where('genre_product_id', $attachmentId)
            ->where('language', 'ja')
            ->count());
    }

    public function test_language_bucket_migration_down_restores_old_genre_metadata_best_effort(): void
    {
        $this->restoreLegacyGenreOrderColumn();

        $product = Product::factory()->create();

        $japaneseGenre = Genre::query()->create([
            'title' => 'Japanese Only',
            'description' => null,
        ]);

        $englishGenre = Genre::query()->create([
            'title' => 'English Only',
            'description' => null,
        ]);

        $customGenre = Genre::query()->create([
            'title' => 'Custom Only',
            'description' => null,
        ]);

        app(ProductGenreSync::class)->sync($product, [
            DlsiteLanguage::Japanese->value => [$japaneseGenre->getKey()],
            DlsiteLanguage::English->value => [$englishGenre->getKey()],
        ], [$customGenre->getKey()]);

        // Roll migrations back in reverse order: canonical ja -> historical jp, then remove the bucket table.
        $this->runLanguageCodeNormalizationMigrationDown();
        $this->runLanguageBucketMigrationDown();

        $this->assertTrue(Schema::hasColumn('genres', 'type'));
        $this->assertTrue(Schema::hasColumn('genres', 'language'));
        $this->assertFalse(Schema::hasTable('genre_product_languages'));

        $japaneseGenre = DB::table('genres')->where('id', $japaneseGenre->id)->firstOrFail();
        $englishGenre = DB::table('genres')->where('id', $englishGenre->id)->firstOrFail();
        $customGenre = DB::table('genres')->where('id', $customGenre->id)->firstOrFail();

        $this->assertSame(Genre::TYPE_AUTO_GENERATED_JAPANESE, $japaneseGenre->type);
        $this->assertSame('jp', $japaneseGenre->language);
        $this->assertSame(Genre::TYPE_AUTO_GENERATED_ENGLISH, $englishGenre->type);
        $this->assertSame(DlsiteLanguage::English->value, $englishGenre->language);
        $this->assertSame(Genre::TYPE_CUSTOM, $customGenre->type);
        $this->assertSame(DlsiteLanguage::English->value, $customGenre->language);
    }

    private function runGenreIdMigration(): void
    {
        $migration = require database_path('migrations/2026_03_16_160000_convert_product_genre_titles_to_ids.php');

        $migration->up();
    }

    private function runLanguageBucketMigration(): void
    {
        $migration = require database_path('migrations/2026_05_24_000000_create_genre_product_languages_table.php');

        $migration->up();
    }

    private function runLanguageCodeNormalizationMigration(): void
    {
        $migration = require database_path('migrations/2026_11_15_000000_normalize_dlsite_language_codes.php');

        $migration->up();
    }

    private function runLanguageCodeNormalizationMigrationDown(): void
    {
        $migration = require database_path('migrations/2026_11_15_000000_normalize_dlsite_language_codes.php');

        $migration->down();
    }

    private function runLanguageBucketMigrationDown(): void
    {
        $migration = require database_path('migrations/2026_05_24_000000_create_genre_product_languages_table.php');

        $migration->down();
    }

    private function restoreLegacyGenreColumns(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'genre')) {
                $table->json('genre')->nullable();
            }

            if (! Schema::hasColumn('products', 'genre_english')) {
                $table->json('genre_english')->nullable();
            }

            if (! Schema::hasColumn('products', 'genre_custom')) {
                $table->json('genre_custom')->nullable();
            }
        });
    }

    private function restoreGenreMetadataColumns(): void
    {
        $this->restoreLegacyGenreOrderColumn();

        Schema::table('genres', function (Blueprint $table) {
            if (! Schema::hasColumn('genres', 'type')) {
                $table->string('type', 255)->nullable()->after('order');
            }

            if (! Schema::hasColumn('genres', 'language')) {
                $table->string('language', 255)->nullable()->after('type');
            }
        });
    }

    private function restoreLegacyGenreOrderColumn(): void
    {
        if (! Schema::hasColumn('genres', 'order')) {
            Schema::table('genres', function (Blueprint $table): void {
                $table->integer('order')->nullable()->default(null)->after('description');
            });
        }
    }

    private function setLegacyGenres(Product $product, array $japaneseTitles, array $englishTitles, array $customTitles): void
    {
        DB::table('products')
            ->where('id', $product->id)
            ->update([
                'genre' => json_encode($japaneseTitles),
                'genre_english' => json_encode($englishTitles),
                'genre_custom' => json_encode($customTitles),
            ]);
    }

    private function assertPivotLanguages(Product $product, int|string $genreId, array $languages): void
    {
        $pivotId = DB::table('genre_product')
            ->where('product_id', $product->id)
            ->where('genre_id', $genreId)
            ->value('id');

        $this->assertEqualsCanonicalizing(
            $languages,
            DB::table('genre_product_languages')
                ->where('genre_product_id', $pivotId)
                ->pluck('language')
                ->all()
        );
    }
}
