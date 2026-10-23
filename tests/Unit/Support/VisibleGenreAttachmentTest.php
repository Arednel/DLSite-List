<?php

namespace Tests\Unit\Support;

use App\Enums\FetchedTagLanguage;
use App\Enums\UiLanguage;
use App\Models\Genre;
use App\Models\Option;
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
    public function test_default_visibility_returns_custom_and_followed_ui_language_fetched_attachments(
        UiLanguage $uiLanguage,
        string $expectedFetchedTitle,
    ): void {
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

    public function test_manual_fetched_tag_language_can_differ_from_the_ui_language(): void
    {
        App::setLocale(UiLanguage::English->value);
        Option::setFetchedTagLanguageFollowsUi(false);
        Option::setFetchedTagLanguage(FetchedTagLanguage::Japanese);

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
        ], $this->visibleTitlesFor($product));
    }

    public function test_explicit_language_overrides_the_selected_fetched_tag_language(): void
    {
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

    public function test_traditional_chinese_shows_only_traditional_chinese_rows_when_the_work_has_them(): void
    {
        $product = Product::factory()->create();
        $english = Genre::query()->create(['title' => 'Healing']);
        $japanese = Genre::query()->create(['title' => '癒し']);
        $traditionalChinese = Genre::query()->create(['title' => '療癒']);
        $shared = Genre::query()->create(['title' => 'ASMR']);

        $this->attachGenre($product, $english, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_ENGLISH]);
        $this->attachGenre($product, $japanese, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_JAPANESE]);
        $this->attachGenre($product, $traditionalChinese, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_TRADITIONAL_CHINESE]);
        $this->attachGenre($product, $shared, Genre::PIVOT_SOURCE_FETCHED, [
            Genre::LANGUAGE_JAPANESE,
            Genre::LANGUAGE_ENGLISH,
            Genre::LANGUAGE_TRADITIONAL_CHINESE,
        ]);

        $this->assertSame(['ASMR', '療癒'], $this->visibleTitlesFor($product, Genre::LANGUAGE_TRADITIONAL_CHINESE));
        $this->assertSame(['ASMR', 'Healing'], $this->visibleTitlesFor($product, Genre::LANGUAGE_ENGLISH));
        $this->assertSame(Genre::LANGUAGE_TRADITIONAL_CHINESE, VisibleGenreAttachment::resolvedLanguage(
            $product->getKey(),
            Genre::LANGUAGE_TRADITIONAL_CHINESE,
        ));
    }

    public function test_traditional_chinese_falls_back_per_work_in_one_query(): void
    {
        $withTraditionalChinese = Product::factory()->create();
        $englishOnly = Product::factory()->create();
        $japaneseOnly = Product::factory()->create();
        $custom = Genre::query()->create(['title' => 'Custom Visible']);
        $english = Genre::query()->create(['title' => 'English Fallback']);
        $japanese = Genre::query()->create(['title' => 'Japanese Fallback']);
        $traditionalChinese = Genre::query()->create(['title' => '環繞音']);

        $this->attachGenre($withTraditionalChinese, $english, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_ENGLISH]);
        $this->attachGenre($withTraditionalChinese, $traditionalChinese, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_TRADITIONAL_CHINESE]);
        $this->attachGenre($englishOnly, $custom, Genre::PIVOT_SOURCE_CUSTOM);
        $this->attachGenre($englishOnly, $english, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_ENGLISH]);
        $this->attachGenre($englishOnly, $japanese, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_JAPANESE]);
        $this->attachGenre($japaneseOnly, $japanese, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_JAPANESE]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $titles = DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->whereIn('genre_product.product_id', [
                $withTraditionalChinese->getKey(),
                $englishOnly->getKey(),
                $japaneseOnly->getKey(),
            ])
            ->where(VisibleGenreAttachment::query(Genre::LANGUAGE_TRADITIONAL_CHINESE))
            ->orderBy('genres.title')
            ->get(['genre_product.product_id', 'genres.title'])
            ->groupBy('product_id')
            ->map(fn($rows): array => $rows->pluck('title')->all());

        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(['環繞音'], $titles[$withTraditionalChinese->getKey()]);
        $this->assertSame(['Custom Visible', 'English Fallback'], $titles[$englishOnly->getKey()]);
        $this->assertSame(['Japanese Fallback'], $titles[$japaneseOnly->getKey()]);
        $this->assertSame(Genre::LANGUAGE_ENGLISH, VisibleGenreAttachment::resolvedLanguage(
            $englishOnly->getKey(),
            Genre::LANGUAGE_TRADITIONAL_CHINESE,
        ));
        $this->assertSame(Genre::LANGUAGE_JAPANESE, VisibleGenreAttachment::resolvedLanguage(
            $japaneseOnly->getKey(),
            Genre::LANGUAGE_TRADITIONAL_CHINESE,
        ));
    }

    public function test_english_and_japanese_do_not_fall_back(): void
    {
        $product = Product::factory()->create();
        $traditionalChinese = Genre::query()->create(['title' => '萌']);

        $this->attachGenre($product, $traditionalChinese, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_TRADITIONAL_CHINESE]);

        $this->assertSame([], $this->visibleTitlesFor($product, Genre::LANGUAGE_ENGLISH));
        $this->assertSame([], $this->visibleTitlesFor($product, Genre::LANGUAGE_JAPANESE));
        $this->assertSame(Genre::LANGUAGE_ENGLISH, VisibleGenreAttachment::resolvedLanguage(
            $product->getKey(),
            Genre::LANGUAGE_ENGLISH,
        ));
    }

    public function test_a_custom_traditional_chinese_title_does_not_disable_the_english_fallback(): void
    {
        $product = Product::factory()->create();
        $custom = Genre::query()->create(['title' => '自訂標籤']);
        $english = Genre::query()->create(['title' => 'English Fallback']);

        $this->attachGenre($product, $custom, Genre::PIVOT_SOURCE_CUSTOM, [Genre::LANGUAGE_TRADITIONAL_CHINESE]);
        $this->attachGenre($product, $english, Genre::PIVOT_SOURCE_FETCHED, [Genre::LANGUAGE_ENGLISH]);

        $this->assertSame(['English Fallback', '自訂標籤'], $this->visibleTitlesFor($product, Genre::LANGUAGE_TRADITIONAL_CHINESE));
    }

    public static function localeVisibilityProvider(): iterable
    {
        yield 'English' => [UiLanguage::English, 'English Fetched'];
        yield 'Japanese' => [UiLanguage::Japanese, 'Japanese Fetched'];
        yield 'Traditional Chinese falls back to English fetched tags' => [UiLanguage::TraditionalChinese, 'English Fetched'];
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
