<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductGenreMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_moves_legacy_genre_json_into_pivot_and_drops_columns(): void
    {
        $now = now();
        $this->restoreLegacyGenreColumns();

        $sharedGenreId = DB::table('genres')->insertGetId([
            'group_id' => null,
            'title' => 'Shared Genre',
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

        $this->assertFalse(Schema::hasColumn('products', 'genre'));
        $this->assertFalse(Schema::hasColumn('products', 'genre_english'));
        $this->assertFalse(Schema::hasColumn('products', 'genre_custom'));

        $japaneseGenre = DB::table('genres')->where('title', 'Japanese Only')->firstOrFail();
        $customGenre = DB::table('genres')->where('title', 'Custom Only')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$japaneseGenre->id, $sharedGenreId, $customGenre->id],
            DB::table('genre_product')
                ->where('product_id', $product->id)
                ->pluck('genre_id')
                ->all()
        );
        $this->assertSame(Genre::TYPE_AUTO_GENERATED_JAPANESE, $japaneseGenre->type);
        $this->assertSame(Genre::LANGUAGE_JAPANESE, $japaneseGenre->language);
        $this->assertSame(Genre::TYPE_CUSTOM, $customGenre->type);
        $this->assertSame(Genre::LANGUAGE_ENGLISH, $customGenre->language);
        $this->assertSame(
            1,
            DB::table('genres')->where('title', 'Shared Genre')->count()
        );
    }

    public function test_migration_promotes_existing_custom_genre_when_auto_generated_title_matches_it(): void
    {
        $now = now();
        $this->restoreLegacyGenreColumns();

        $sharedGenreId = DB::table('genres')->insertGetId([
            'group_id' => null,
            'title' => 'Shared Genre',
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

        $sharedGenre = DB::table('genres')->where('id', $sharedGenreId)->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$sharedGenreId],
            DB::table('genre_product')
                ->where('product_id', $product->id)
                ->pluck('genre_id')
                ->all()
        );
        $this->assertSame(Genre::TYPE_AUTO_GENERATED_ENGLISH, $sharedGenre->type);
        $this->assertSame(Genre::LANGUAGE_ENGLISH, $sharedGenre->language);
    }

    public function test_migration_down_restores_legacy_genre_json_from_pivot_data(): void
    {
        $product = Product::factory()->create();

        $japaneseGenre = Genre::query()->create([
            'group_id' => null,
            'title' => 'Japanese Only',
            'description' => null,
            'order' => null,
            'type' => Genre::TYPE_AUTO_GENERATED_JAPANESE,
            'language' => Genre::LANGUAGE_JAPANESE,
        ]);

        $englishGenre = Genre::query()->create([
            'group_id' => null,
            'title' => 'English Only',
            'description' => null,
            'order' => null,
            'type' => Genre::TYPE_AUTO_GENERATED_ENGLISH,
            'language' => Genre::LANGUAGE_ENGLISH,
        ]);

        $customGenre = Genre::query()->create([
            'group_id' => null,
            'title' => 'Custom Only',
            'description' => null,
            'order' => null,
            'type' => Genre::TYPE_CUSTOM,
            'language' => Genre::LANGUAGE_ENGLISH,
        ]);

        $product->genres()->sync([
            $japaneseGenre->getKey(),
            $englishGenre->getKey(),
            $customGenre->getKey(),
        ]);

        $this->runGenreIdMigrationDown();

        $this->assertTrue(Schema::hasColumn('products', 'genre'));
        $this->assertTrue(Schema::hasColumn('products', 'genre_english'));
        $this->assertTrue(Schema::hasColumn('products', 'genre_custom'));

        $productRow = DB::table('products')->where('id', $product->id)->firstOrFail();

        $this->assertSame(['Japanese Only'], json_decode($productRow->genre, true));
        $this->assertSame(['English Only'], json_decode($productRow->genre_english, true));
        $this->assertSame(['Custom Only'], json_decode($productRow->genre_custom, true));
    }

    private function runGenreIdMigration(): void
    {
        $migration = require database_path('migrations/2026_03_16_160000_convert_product_genre_titles_to_ids.php');

        $migration->up();
    }

    private function runGenreIdMigrationDown(): void
    {
        $migration = require database_path('migrations/2026_03_16_160000_convert_product_genre_titles_to_ids.php');

        $migration->down();
    }

    private function restoreLegacyGenreColumns(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'genre')) {
                $table->json('genre')->nullable();
            }

            if (!Schema::hasColumn('products', 'genre_english')) {
                $table->json('genre_english')->nullable();
            }

            if (!Schema::hasColumn('products', 'genre_custom')) {
                $table->json('genre_custom')->nullable();
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
}
