<?php

namespace Tests\Unit\Support;

use App\Models\Genre;
use App\Models\Product;
use App\Support\VisibleGenreAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VisibleGenreAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_custom_and_fetched_english_attachments(): void
    {
        $product = Product::factory()->create();
        $custom = Genre::query()->create(['title' => 'Custom Visible']);
        $english = Genre::query()->create(['title' => 'English Visible']);

        $this->attachGenre($product, $custom, Genre::PIVOT_SOURCE_CUSTOM);
        $this->attachGenre($product, $english, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_ENGLISH]);

        $this->assertSame([
            'Custom Visible',
            'English Visible',
        ], $this->visibleTitlesFor($product));
    }

    public function test_it_hides_japanese_only_fetched_attachments(): void
    {
        $product = Product::factory()->create();
        $japanese = Genre::query()->create(['title' => 'Japanese Hidden']);

        $this->attachGenre($product, $japanese, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_JAPANESE]);

        $this->assertSame([], $this->visibleTitlesFor($product));
    }

    public function test_it_keeps_same_title_japanese_and_english_attachment_visible_once(): void
    {
        $product = Product::factory()->create();
        $shared = Genre::query()->create(['title' => 'ASMR']);

        $this->attachGenre($product, $shared, Genre::PIVOT_SOURCE_FETCHED, [
            Genre::LANGUAGE_JAPANESE,
            Genre::LANGUAGE_ENGLISH,
        ]);

        $this->assertSame(['ASMR'], $this->visibleTitlesFor($product));
    }

    /**
     * @param  list<string>  $languages
     */
    private function attachGenre(Product $product, Genre $genre, string $source, array $languages = []): int
    {
        $now = now();
        $pivotId = DB::table('genre_product')->insertGetId([
            'product_id' => $product->getKey(),
            'genre_id' => $genre->getKey(),
            'source' => $source,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($languages as $language) {
            DB::table('genre_product_languages')->insert([
                'genre_product_id' => $pivotId,
                'language' => $language,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $pivotId;
    }

    /**
     * @return list<string>
     */
    private function visibleTitlesFor(Product $product): array
    {
        $query = DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->where('genre_product.product_id', $product->getKey())
            ->where(VisibleGenreAttachment::query())
            ->orderBy('genres.title');

        return $query->pluck('genres.title')->all();
    }
}
