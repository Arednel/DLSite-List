<?php

namespace Tests\Unit\Support;

use App\Models\Genre;
use App\Models\Product;
use App\Support\ProductGenreSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductGenreSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_one_fetched_genre_with_multiple_language_rows(): void
    {
        $product = Product::factory()->create();
        $genre = Genre::query()->create([
            'title' => 'ASMR',
        ]);

        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_JAPANESE => [$genre->getKey()],
            Genre::LANGUAGE_ENGLISH => [$genre->getKey()],
        ], []);

        $pivot = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('genre_id', $genre->getKey())
            ->first();

        $this->assertSame(Genre::PIVOT_SOURCE_FETCHED, $pivot->source);
        $this->assertEqualsCanonicalizing(
            [Genre::LANGUAGE_JAPANESE, Genre::LANGUAGE_ENGLISH],
            DB::table('genre_product_languages')
                ->where('genre_product_id', $pivot->id)
                ->pluck('language')
                ->all()
        );
    }

    public function test_custom_sync_preserves_existing_fetched_language_rows(): void
    {
        $product = Product::factory()->create();
        $fetchedGenre = Genre::query()->create(['title' => 'Fetched']);
        $customGenre = Genre::query()->create(['title' => 'Custom']);
        $sync = app(ProductGenreSync::class);

        $sync->sync($product, [
            Genre::LANGUAGE_ENGLISH => [$fetchedGenre->getKey()],
        ], []);

        $changed = $sync->syncCustom($product, [$customGenre->getKey()]);

        $this->assertTrue($changed);
        $product->refresh()->load(['englishGenres', 'customGenres']);
        $this->assertSame(['Fetched'], $product->englishGenres->pluck('title')->all());
        $this->assertSame(['Custom'], $product->customGenres->pluck('title')->all());
    }

    public function test_editable_english_sync_replaces_english_and_preserves_japanese_rows(): void
    {
        $product = Product::factory()->create();
        $japaneseGenre = Genre::query()->create(['title' => 'Japanese Only']);
        $oldEnglishGenre = Genre::query()->create(['title' => 'Old English']);
        $newEnglishGenre = Genre::query()->create(['title' => 'New English']);
        $sync = app(ProductGenreSync::class);

        $sync->sync($product, [
            Genre::LANGUAGE_JAPANESE => [$japaneseGenre->getKey()],
            Genre::LANGUAGE_ENGLISH => [$oldEnglishGenre->getKey()],
        ], []);

        $changed = $sync->syncEditableTagBuckets($product, [$newEnglishGenre->getKey()], []);

        $this->assertTrue($changed);
        $product->refresh()->load(['japaneseGenres', 'englishGenres']);
        $this->assertSame(['Japanese Only'], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['New English'], $product->englishGenres->pluck('title')->all());
        $this->assertDatabaseMissing('genre_product', [
            'product_id' => $product->getKey(),
            'genre_id' => $oldEnglishGenre->getKey(),
        ]);
    }

    public function test_editable_english_sync_keeps_fetched_over_custom_precedence(): void
    {
        $product = Product::factory()->create();
        $sharedGenre = Genre::query()->create(['title' => 'Shared']);
        $customGenre = Genre::query()->create(['title' => 'Custom']);

        app(ProductGenreSync::class)->syncEditableTagBuckets(
            $product,
            [$sharedGenre->getKey()],
            [$sharedGenre->getKey(), $customGenre->getKey()],
        );

        $product->refresh()->load(['englishGenres', 'customGenres']);
        $this->assertSame(['Shared'], $product->englishGenres->pluck('title')->all());
        $this->assertSame(['Custom'], $product->customGenres->pluck('title')->all());

        $pivot = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('genre_id', $sharedGenre->getKey())
            ->first();

        $this->assertSame(Genre::PIVOT_SOURCE_FETCHED, $pivot->source);
    }
}
