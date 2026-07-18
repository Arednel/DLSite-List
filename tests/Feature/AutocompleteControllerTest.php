<?php

namespace Tests\Feature;

use App\Enums\AutocompleteOrder;
use App\Enums\ProductField;
use App\Models\Genre;
use App\Models\GenreGroup;
use App\Models\Option;
use App\Models\Product;
use App\Support\ProductGenreSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutocompleteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_suggestions_search_all_languages_and_order_by_usage_count(): void
    {
        $popularEnglish = $this->createGenre('Office Lady', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $lessUsedCustom = $this->createGenre('Office Chair', Genre::TYPE_CUSTOM);
        $japanese = $this->createGenre('テストタグ', Genre::TYPE_AUTO_GENERATED_JAPANESE);

        $this->attachGenreToProducts($popularEnglish, Genre::TYPE_AUTO_GENERATED_ENGLISH, 3);
        $this->attachGenreToProducts($lessUsedCustom, Genre::TYPE_CUSTOM, 1);
        $this->attachGenreToProducts($japanese, Genre::TYPE_AUTO_GENERATED_JAPANESE, 1);

        $response = $this->getJson('/autocomplete/tags?q=off');

        $response->assertOk()
            ->assertJsonPath('0.value', 'Office Lady')
            ->assertJsonPath('0.label', 'Office Lady')
            ->assertJsonPath('0.count', 3)
            ->assertJsonPath('0.type', 'tag')
            ->assertJsonPath('1.value', 'Office Chair')
            ->assertJsonPath('1.count', 1);

        $this->getJson('/autocomplete/tags?q=' . rawurlencode('テスト'))
            ->assertOk()
            ->assertJsonFragment([
                'value' => 'テストタグ',
                'label' => 'テストタグ',
                'count' => 1,
                'type' => 'tag',
            ]);
    }

    public function test_tag_suggestions_can_prioritize_first_word_matches(): void
    {
        Option::setTagAutocompleteOrder(AutocompleteOrder::FirstWord);

        $laterWordPopular = $this->createGenre('One Two', Genre::TYPE_CUSTOM);
        $firstWordLessUsed = $this->createGenre('Twenty', Genre::TYPE_CUSTOM);

        $this->attachGenreToProducts($laterWordPopular, Genre::TYPE_CUSTOM, 3);
        $this->attachGenreToProducts($firstWordLessUsed, Genre::TYPE_CUSTOM, 1);

        $response = $this->getJson('/autocomplete/tags?q=tw');

        $response->assertOk()
            ->assertJsonPath('0.value', 'Twenty')
            ->assertJsonPath('0.count', 1)
            ->assertJsonPath('1.value', 'One Two')
            ->assertJsonPath('1.count', 3);
    }

    public function test_tag_suggestions_use_word_prefix_matching_for_latin_text(): void
    {
        $officeLady = $this->createGenre('Office Lady', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $milady = $this->createGenre('Milady', Genre::TYPE_AUTO_GENERATED_ENGLISH);

        $this->attachGenreToProducts($officeLady, Genre::TYPE_AUTO_GENERATED_ENGLISH, 1);
        $this->attachGenreToProducts($milady, Genre::TYPE_AUTO_GENERATED_ENGLISH, 1);

        $values = collect($this->getJson('/autocomplete/tags?q=lady')->json())
            ->pluck('value')
            ->all();

        $this->assertContains('Office Lady', $values);
        $this->assertNotContains('Milady', $values);
    }

    public function test_tag_suggestions_are_limited(): void
    {
        foreach (range(1, 25) as $index) {
            $this->createGenre("Limit Tag {$index}", Genre::TYPE_CUSTOM);
        }

        $this->getJson('/autocomplete/tags?q=limit')
            ->assertOk()
            ->assertJsonCount(20);
    }

    public function test_tag_suggestions_include_colors_only_when_autocomplete_surface_is_enabled(): void
    {
        $genre = $this->createGenre('Colored Suggestion', Genre::TYPE_CUSTOM);
        Genre::query()->whereKey($genre->getKey())->update([
            'color' => '#aa3366',
            'text_color' => '#111111',
        ]);

        $defaultResponse = $this->getJson('/autocomplete/tags?q=colored')
            ->assertOk()
            ->assertJsonPath('0.value', 'Colored Suggestion');

        $this->assertArrayNotHasKey('color', $defaultResponse->json('0'));
        $this->assertArrayNotHasKey('text_color', $defaultResponse->json('0'));

        Option::setTagColorSurfaces([Option::TAG_COLOR_SURFACE_AUTOCOMPLETE => true]);

        $this->getJson('/autocomplete/tags?q=colored')
            ->assertOk()
            ->assertJsonPath('0.color', '#aa3366')
            ->assertJsonPath('0.text_color', '#111111');
    }

    public function test_autocomplete_script_uses_independent_color_classes_without_marker_circle(): void
    {
        $script = file_get_contents(public_path('scripts/autocomplete-text.js'));

        $this->assertStringContainsString('autocomplete-option--background-colored', $script);
        $this->assertStringContainsString('autocomplete-option--text-colored', $script);
        $this->assertStringNotContainsString('autocomplete-option__color', $script);
    }

    public function test_tag_suggestion_color_uses_group_color_over_tag_color(): void
    {
        $group = GenreGroup::query()->create([
            'title' => 'Suggestion Color Group',
            'description' => null,
            'order' => 1,
            'color' => '#112233',
            'text_color' => '#eeeeee',
        ]);
        $genre = $this->createGenre('Grouped Colored Suggestion', Genre::TYPE_CUSTOM);
        Genre::query()->whereKey($genre->getKey())->update([
            'color' => '#aa3366',
            'text_color' => '#111111',
        ]);
        DB::table('genre_group_genre')->insert([
            'genre_group_id' => $group->getKey(),
            'genre_id' => $genre->getKey(),
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Option::setTagColorSurfaces([Option::TAG_COLOR_SURFACE_AUTOCOMPLETE => true]);

        $this->getJson('/autocomplete/tags?q=grouped')
            ->assertOk()
            ->assertJsonPath('0.color', '#112233')
            ->assertJsonPath('0.text_color', '#eeeeee');
    }

    public function test_series_suggestions_return_distinct_non_empty_series_ordered_by_usage_count(): void
    {
        Product::factory()->count(2)->create(['series' => 'Office Series']);
        Product::factory()->create(['series' => 'Home Series']);
        Product::factory()->create(['series' => 'Milady Collection']);
        Product::factory()->create(['series' => null]);
        Product::factory()->create(['series' => '']);

        $response = $this->getJson('/autocomplete/series?q=series');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.value', 'Office Series')
            ->assertJsonPath('0.label', 'Office Series')
            ->assertJsonPath('0.count', 2)
            ->assertJsonPath('0.type', 'series')
            ->assertJsonPath('1.value', 'Home Series')
            ->assertJsonPath('1.count', 1);
    }

    public function test_series_suggestions_have_their_own_first_word_order_setting(): void
    {
        Option::setTagAutocompleteOrder(AutocompleteOrder::FirstWord);
        Product::factory()->count(3)->create(['series' => 'One Two Series']);
        Product::factory()->create(['series' => 'Twenty Series']);

        $this->getJson('/autocomplete/series?q=tw')
            ->assertOk()
            ->assertJsonPath('0.value', 'One Two Series')
            ->assertJsonPath('0.count', 3)
            ->assertJsonPath('1.value', 'Twenty Series')
            ->assertJsonPath('1.count', 1);

        Option::setSeriesAutocompleteOrder(AutocompleteOrder::FirstWord);

        $this->getJson('/autocomplete/series?q=tw')
            ->assertOk()
            ->assertJsonPath('0.value', 'Twenty Series')
            ->assertJsonPath('0.count', 1)
            ->assertJsonPath('1.value', 'One Two Series')
            ->assertJsonPath('1.count', 3);
    }

    public function test_autocomplete_assets_and_field_attributes_render_on_index_and_create(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('css/autocomplete.css', false)
            ->assertSee('scripts/autocomplete-text.js', false)
            ->assertSee('id="filter_tags"', false)
            ->assertSee('data-autocomplete-source="tags"', false)
            ->assertSee('data-autocomplete-mode="csv"', false)
            ->assertSee('id="filter_series"', false)
            ->assertSee('data-autocomplete-source="series"', false)
            ->assertSee('data-autocomplete-mode="single"', false);

        $this->get('/create')
            ->assertOk()
            ->assertSee('css/autocomplete.css', false)
            ->assertSee('scripts/autocomplete-text.js', false)
            ->assertSee('id="genre_custom"', false)
            ->assertSee('data-autocomplete-source="tags"', false)
            ->assertSee('id="series"', false)
            ->assertSee('data-autocomplete-source="series"', false);
    }

    public function test_autocomplete_assets_and_generic_fetched_field_attributes_render_on_edit(): void
    {
        Option::setEditFieldLayout(array_map(
            fn(array $row): array => $row['field'] === ProductField::FetchedTags->value
                ? [...$row, 'editable' => true]
                : $row,
            Option::editFieldLayout()
        ));

        $product = Product::factory()->create();

        $this->get("/edit/{$product->id}")
            ->assertOk()
            ->assertSee('css/autocomplete.css', false)
            ->assertSee('scripts/autocomplete-text.js', false)
            ->assertSee('id="genre_fetched"', false)
            ->assertSee('data-autocomplete-source="tags"', false)
            ->assertSee('data-autocomplete-mode="csv"', false);
    }

    private function createGenre(string $title, string $type): Genre
    {
        $genre = Genre::query()->create([
            'title' => $title,
            'description' => null,
            'order' => null,
        ]);

        $genre->setAttribute('type', $type);

        return $genre;
    }

    private function attachGenreToProducts(Genre $genre, string $type, int $count): void
    {
        foreach (range(1, $count) as $ignored) {
            $product = Product::factory()->create();

            $fetchedByLanguage = [
                Genre::LANGUAGE_JAPANESE => [],
                Genre::LANGUAGE_ENGLISH => [],
            ];
            $customGenreIds = [];

            match ($type) {
                Genre::TYPE_AUTO_GENERATED_JAPANESE => $fetchedByLanguage[Genre::LANGUAGE_JAPANESE][] = $genre->getKey(),
                Genre::TYPE_AUTO_GENERATED_ENGLISH => $fetchedByLanguage[Genre::LANGUAGE_ENGLISH][] = $genre->getKey(),
                default => $customGenreIds[] = $genre->getKey(),
            };

            app(ProductGenreSync::class)->sync($product, $fetchedByLanguage, $customGenreIds);
        }
    }
}
