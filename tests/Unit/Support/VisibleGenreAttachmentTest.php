<?php

namespace Tests\Unit\Support;

use App\Enums\UiLanguage;
use App\Models\Genre;
use App\Models\Product;
use App\Support\VisibleGenreAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VisibleGenreAttachmentTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('localeVisibilityProvider')]
    public function test_default_visibility_returns_custom_and_current_language_fetched_attachments(
        UiLanguage $uiLanguage,
        string $expectedFetchedTitle,
    ): void
    {
        App::setLocale($uiLanguage->value);

        $product = Product::factory()->create();
        $custom = Genre::query()->create(['title' => 'Custom Visible']);
        $english = Genre::query()->create(['title' => 'English Fetched']);
        $japanese = Genre::query()->create(['title' => 'Japanese Fetched']);

        $this->attachGenre($product, $custom, Genre::PIVOT_SOURCE_CUSTOM);
        $this->attachGenre($product, $english, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_ENGLISH]);
        $this->attachGenre($product, $japanese, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_JAPANESE]);

        $this->assertSame([
            'Custom Visible',
            $expectedFetchedTitle,
        ], $this->visibleTitlesFor($product));
    }

    public function test_explicit_language_overrides_the_current_ui_language(): void
    {
        App::setLocale(UiLanguage::English->value);

        $product = Product::factory()->create();
        $custom = Genre::query()->create(['title' => 'Custom Visible']);
        $english = Genre::query()->create(['title' => 'English Hidden']);
        $japanese = Genre::query()->create(['title' => 'Japanese Visible']);

        $this->attachGenre($product, $custom, Genre::PIVOT_SOURCE_CUSTOM);
        $this->attachGenre($product, $english, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_ENGLISH]);
        $this->attachGenre($product, $japanese, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_JAPANESE]);

        $this->assertSame([
            'Custom Visible',
            'Japanese Visible',
        ], $this->visibleTitlesFor($product, Genre::LANGUAGE_JAPANESE));
    }

    public function test_it_keeps_same_title_japanese_and_english_attachment_visible_once(): void
    {
        $product = Product::factory()->create();
        $shared = Genre::query()->create(['title' => 'ASMR']);

        $this->attachGenre($product, $shared, Genre::PIVOT_SOURCE_FETCHED, [
            Genre::LANGUAGE_JAPANESE,
            Genre::LANGUAGE_ENGLISH,
        ]);

        $this->assertSame(['ASMR'], $this->visibleTitlesFor($product, Genre::LANGUAGE_ENGLISH));
        $this->assertSame(['ASMR'], $this->visibleTitlesFor($product, Genre::LANGUAGE_JAPANESE));
    }

    public function test_a_language_row_does_not_make_a_non_fetched_attachment_visible(): void
    {
        $product = Product::factory()->create();
        $otherSource = Genre::query()->create(['title' => 'Other Source Hidden']);

        $this->attachGenre($product, $otherSource, 'other', [Genre::LANGUAGE_ENGLISH]);

        $this->assertSame([], $this->visibleTitlesFor($product, Genre::LANGUAGE_ENGLISH));
    }

    public static function localeVisibilityProvider(): iterable
    {
        yield 'English' => [UiLanguage::English, 'English Fetched'];
        yield 'Japanese' => [UiLanguage::Japanese, 'Japanese Fetched'];
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
    private function visibleTitlesFor(Product $product, ?string $fetchedLanguage = null): array
    {
        $query = DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->where('genre_product.product_id', $product->getKey())
            ->where(VisibleGenreAttachment::query($fetchedLanguage))
            ->orderBy('genres.title');

        return $query->pluck('genres.title')->all();
    }
}
