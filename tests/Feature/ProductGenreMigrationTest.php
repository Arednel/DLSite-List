<?php

namespace Tests\Feature;

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

    public function test_legacy_genre_json_migration_backfills_language_rows_and_drops_old_metadata(): void
    {
        $now = now();
        Schema::dropIfExists('genre_product_languages');
        $this->restoreLegacyGenreColumns();
        $this->restoreGenreMetadataColumns();

        $sharedGenreId = DB::table('genres')->insertGetId([
            'group_id' => null,
            'title' => 'Shared Genre',
            'title_key' => Genre::titleKey('Shared Genre'),
            'description' => null,
            'order' => null,
            'type' => Genre::TYPE_AUTO_GENERATED_ENGLISH,
            'language' => Genre::LANGUAGE_ENGLISH,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $product = Product::factory()->create();
        $this->setLegacyGenres($product, ['Japanese Only'], ['Shared Genre'], ['Shared Genre', 'Custom Only']);

        $this->runGenreIdMigration();
        $this->runLanguageBucketMigration();

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
        $this->assertPivotLanguages($product, $japaneseGenre->id, [Genre::LANGUAGE_JAPANESE]);
        $this->assertPivotLanguages($product, $sharedGenreId, [Genre::LANGUAGE_ENGLISH]);
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
            'group_id' => null,
            'title' => 'Shared Genre',
            'title_key' => Genre::titleKey('Shared Genre'),
            'description' => null,
            'order' => null,
            'type' => Genre::TYPE_CUSTOM,
            'language' => Genre::LANGUAGE_ENGLISH,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $product = Product::factory()->create();
        $this->setLegacyGenres($product, [], ['Shared Genre'], ['Shared Genre']);

        $this->runGenreIdMigration();
        $this->runLanguageBucketMigration();

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
        $this->assertPivotLanguages($product, $sharedGenreId, [Genre::LANGUAGE_ENGLISH]);
    }

    public function test_language_bucket_migration_down_restores_old_genre_metadata_best_effort(): void
    {
        $product = Product::factory()->create();

        $japaneseGenre = Genre::query()->create([
            'group_id' => null,
            'title' => 'Japanese Only',
            'description' => null,
            'order' => null,
        ]);

        $englishGenre = Genre::query()->create([
            'group_id' => null,
            'title' => 'English Only',
            'description' => null,
            'order' => null,
        ]);

        $customGenre = Genre::query()->create([
            'group_id' => null,
            'title' => 'Custom Only',
            'description' => null,
            'order' => null,
        ]);

        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_JAPANESE => [$japaneseGenre->getKey()],
            Genre::LANGUAGE_ENGLISH => [$englishGenre->getKey()],
        ], [$customGenre->getKey()]);

        $this->runLanguageBucketMigrationDown();

        $this->assertTrue(Schema::hasColumn('genres', 'type'));
        $this->assertTrue(Schema::hasColumn('genres', 'language'));
        $this->assertFalse(Schema::hasTable('genre_product_languages'));

        $japaneseGenre = DB::table('genres')->where('id', $japaneseGenre->id)->firstOrFail();
        $englishGenre = DB::table('genres')->where('id', $englishGenre->id)->firstOrFail();
        $customGenre = DB::table('genres')->where('id', $customGenre->id)->firstOrFail();

        $this->assertSame(Genre::TYPE_AUTO_GENERATED_JAPANESE, $japaneseGenre->type);
        $this->assertSame(Genre::LANGUAGE_JAPANESE, $japaneseGenre->language);
        $this->assertSame(Genre::TYPE_AUTO_GENERATED_ENGLISH, $englishGenre->type);
        $this->assertSame(Genre::LANGUAGE_ENGLISH, $englishGenre->language);
        $this->assertSame(Genre::TYPE_CUSTOM, $customGenre->type);
        $this->assertSame(Genre::LANGUAGE_ENGLISH, $customGenre->language);
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
        Schema::table('genres', function (Blueprint $table) {
            if (! Schema::hasColumn('genres', 'type')) {
                $table->string('type', 255)->nullable()->after('order');
            }

            if (! Schema::hasColumn('genres', 'language')) {
                $table->string('language', 255)->nullable()->after('type');
            }
        });
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
