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
}
