<?php

namespace Tests\Feature;

use App\Enums\ProductPriority;
use App\Enums\ProductProgress;
use App\Enums\ProductReListenValue;
use App\Enums\ProductScore;
use App\Models\Genre;
use App\Models\Option;
use App\Models\Product;
use App\Support\ProductGenreSync;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_by_age_category_progress_series_and_genre(): void
    {
        $token = $this->uniqueToken('FILTER');
        $alphaName = "FILTER_ALPHA_{$token}";
        $betaName = "FILTER_BETA_{$token}";
        $gammaName = "FILTER_GAMMA_{$token}";
        $calmFocus = $this->createGenre('CalmFocus', Genre::TYPE_CUSTOM);
        $nightTag = $this->createGenre('NightTag', Genre::TYPE_CUSTOM);
        $dayTag = $this->createGenre('DayTag', Genre::TYPE_CUSTOM);
        $ambientAlpha = $this->createGenre('AmbientAlpha', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $darkNight = $this->createGenre('DarkNight', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $mindfulGenre = $this->createGenre('MindfulGenre', Genre::TYPE_AUTO_GENERATED_ENGLISH);

        $alpha = Product::factory()->create([
            'work_name' => $alphaName,
            'age_category' => 'ALL_AGES',
            'progress' => 'Listening',
            'series' => 'SERIES_ALPHA',
        ]);
        $this->attachGenres($alpha, [$ambientAlpha, $calmFocus]);

        $beta = Product::factory()->create([
            'work_name' => $betaName,
            'age_category' => 'R18',
            'progress' => 'Completed',
            'series' => 'SERIES_BETA',
        ]);
        $this->attachGenres($beta, [$darkNight, $nightTag]);

        $gamma = Product::factory()->create([
            'work_name' => $gammaName,
            'age_category' => 'ALL_AGES',
            'progress' => 'Completed',
            'series' => 'SERIES_ALPHA',
        ]);
        $this->attachGenres($gamma, [$mindfulGenre, $dayTag]);

        $this->get('/?age_category=ALL_AGES')
            ->assertOk()
            ->assertSee($alpha->work_name)
            ->assertSee($gamma->work_name)
            ->assertDontSee($beta->work_name);

        $this->get('/?progress=Completed')
            ->assertOk()
            ->assertSee($beta->work_name)
            ->assertSee($gamma->work_name)
            ->assertDontSee($alpha->work_name);

        $this->get('/?series=SERIES_ALPHA')
            ->assertOk()
            ->assertSee($alpha->work_name)
            ->assertSee($gamma->work_name)
            ->assertDontSee($beta->work_name);

        $this->get('/?genre=CalmFocus')
            ->assertOk()
            ->assertSee($alpha->work_name)
            ->assertDontSee($beta->work_name)
            ->assertDontSee($gamma->work_name);

        $this->get('/?genre=MindfulGenre')
            ->assertOk()
            ->assertSee($gamma->work_name)
            ->assertDontSee($alpha->work_name)
            ->assertDontSee($beta->work_name);
    }

    public function test_index_search_matches_id_titles_series_and_related_genres(): void
    {
        $token = $this->uniqueToken('SEARCH');
        $jpToken = "SEARCH_JP_{$token}";
        $enToken = "SEARCH_EN_{$token}";
        $seriesToken = "SEARCH_SERIES_{$token}";
        $genreToken = "SEARCH_GENRE_{$token}";
        $noiseToken = "SEARCH_NOISE_{$token}";
        $customToken = "SEARCH_CUSTOM_{$token}";
        $genre = $this->createGenre($genreToken, Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $customGenre = $this->createGenre($customToken, Genre::TYPE_CUSTOM);
        $hiddenJapaneseGenre = $this->createGenre("SEARCH_HIDDEN_JP_{$token}", Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $noiseGenre = $this->createGenre("SEARCH_NOISE_GENRE_{$token}", Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $noiseCustomGenre = $this->createGenre("SEARCH_NOISE_CUSTOM_{$token}", Genre::TYPE_CUSTOM);

        $target = Product::factory()->create([
            'work_name' => $jpToken,
            'work_name_english' => $enToken,
            'series' => $seriesToken,
        ]);
        $this->attachGenres($target, [$genre, $customGenre]);

        $hiddenJapanese = Product::factory()->create([
            'work_name' => "SEARCH_HIDDEN_PRODUCT_{$token}",
            'work_name_english' => null,
            'series' => null,
        ]);
        $this->attachGenres($hiddenJapanese, [$hiddenJapaneseGenre]);

        $noise = Product::factory()->create([
            'work_name' => $noiseToken,
            'work_name_english' => "SEARCH_NOISE_EN_{$token}",
            'series' => "SEARCH_NOISE_SERIES_{$token}",
        ]);
        $this->attachGenres($noise, [$noiseGenre, $noiseCustomGenre]);

        $this->get('/?search=' . strtolower($target->id))
            ->assertOk()
            ->assertSee($target->work_name)
            ->assertDontSee($noise->work_name);

        $this->get('/?search=' . strtolower($jpToken))
            ->assertOk()
            ->assertSee($target->work_name)
            ->assertDontSee($noise->work_name);

        $this->get('/?search=' . strtolower($enToken))
            ->assertOk()
            ->assertSee($target->work_name_english)
            ->assertDontSee($noise->work_name_english);

        $this->get('/?search=' . strtolower($seriesToken))
            ->assertOk()
            ->assertSee($target->series)
            ->assertDontSee($noise->series);

        $this->get('/?search=' . strtolower($genreToken))
            ->assertOk()
            ->assertSee($target->work_name)
            ->assertDontSee($noise->work_name);

        $this->get('/?search=' . strtolower($customToken))
            ->assertOk()
            ->assertSee($target->work_name)
            ->assertDontSee($noise->work_name);

        $this->get('/?search=' . strtolower($hiddenJapaneseGenre->title))
            ->assertOk()
            ->assertDontSee($hiddenJapanese->work_name);
    }

    public function test_index_ignores_invalid_filter_values(): void
    {
        $token = $this->uniqueToken('INVALID_FILTER');
        $alpha = Product::factory()->create(['work_name' => "INVALID_FILTER_ALPHA_{$token}"]);
        $beta = Product::factory()->create(['work_name' => "INVALID_FILTER_BETA_{$token}"]);

        $this->get('/?age_category=NOT_VALID&progress=NOT_VALID_EITHER')
            ->assertOk()
            ->assertSee('All ASMR')
            ->assertSee($alpha->work_name)
            ->assertSee($beta->work_name);
    }

    public function test_index_displays_titles_from_related_genres(): void
    {
        $japaneseGenre = $this->createGenre('Resolved Japanese Tag', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $englishGenre = $this->createGenre('Resolved English Tag', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $customGenre = $this->createGenre('Resolved Custom Tag', Genre::TYPE_CUSTOM);

        $product = Product::factory()->create([
            'work_name' => 'RESOLVED_GENRE_DISPLAY_TOKEN',
        ]);
        $this->attachGenres($product, [$japaneseGenre, $englishGenre, $customGenre]);

        $this->get('/')
            ->assertOk()
            ->assertSee($product->work_name)
            ->assertSee('Resolved English Tag')
            ->assertSee('Resolved Custom Tag')
            ->assertSee('data-list-menu-toggle', false)
            ->assertDontSee('Resolved Japanese Tag');
    }

    public function test_index_filters_by_genre_id_from_view_links(): void
    {
        $sharedGenre = $this->createGenre('Linked Tag', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $hiddenJapaneseGenre = $this->createGenre('Hidden Linked JP Tag', Genre::TYPE_AUTO_GENERATED_JAPANESE);

        $matching = Product::factory()->create([
            'work_name' => 'GENRE_ID_MATCH_TOKEN',
        ]);
        $this->attachGenres($matching, [$sharedGenre]);

        $hiddenJapanese = Product::factory()->create([
            'work_name' => 'GENRE_ID_HIDDEN_JP_TOKEN',
        ]);
        $this->attachGenres($hiddenJapanese, [$hiddenJapaneseGenre]);

        $noise = Product::factory()->create([
            'work_name' => 'GENRE_ID_NOISE_TOKEN',
        ]);

        $this->get('/?genre=' . $sharedGenre->getKey())
            ->assertOk()
            ->assertSee($matching->work_name)
            ->assertDontSee($noise->work_name);

        $this->get('/?genre=' . $hiddenJapaneseGenre->getKey())
            ->assertOk()
            ->assertDontSee($hiddenJapanese->work_name);
    }

    public function test_progress_links_drop_current_genre_filter_from_request(): void
    {
        $response = $this->get('/?genre=36&search=rain&series=SERIES_ALPHA');

        $response->assertOk()
            ->assertSee('href="/?search=rain&amp;series=SERIES_ALPHA"', false)
            ->assertSee('href="/?search=rain&amp;series=SERIES_ALPHA&amp;progress=Listening"', false)
            ->assertSee('href="/?search=rain&amp;series=SERIES_ALPHA&amp;progress=Completed"', false)
            ->assertSee('href="/?search=rain&amp;series=SERIES_ALPHA&amp;progress=Plan%20to%20Listen"', false)
            ->assertDontSee('progress=Listening&amp;genre=36', false)
            ->assertDontSee('progress=Completed&amp;genre=36', false)
            ->assertDontSee('progress=Plan%20to%20Listen&amp;genre=36', false);
    }

    public function test_index_filters_by_extended_server_side_filter_modal_fields(): void
    {
        $matching = Product::factory()->create([
            'work_name' => 'FILTER_MODAL_MATCH_TOKEN',
            'score' => 8,
            'priority' => 2,
            'series' => 'FILTER_MODAL_SERIES_TOKEN',
            'notes' => 'FILTER_MODAL_NOTES_TOKEN',
            'num_re_listen_times' => 3,
            're_listen_value' => 4,
        ]);

        $wrongPriority = Product::factory()->create([
            'work_name' => 'FILTER_MODAL_WRONG_PRIORITY_TOKEN',
            'score' => 8,
            'priority' => 1,
            'series' => 'FILTER_MODAL_SERIES_TOKEN',
            'notes' => 'FILTER_MODAL_NOTES_TOKEN',
            'num_re_listen_times' => 3,
            're_listen_value' => 4,
        ]);

        $wrongScore = Product::factory()->create([
            'work_name' => 'FILTER_MODAL_WRONG_SCORE_TOKEN',
            'score' => 7,
            'priority' => 2,
            'series' => 'FILTER_MODAL_SERIES_TOKEN',
            'notes' => 'FILTER_MODAL_NOTES_TOKEN',
            'num_re_listen_times' => 3,
            're_listen_value' => 4,
        ]);

        $wrongNotes = Product::factory()->create([
            'work_name' => 'FILTER_MODAL_WRONG_NOTES_TOKEN',
            'score' => 8,
            'priority' => 2,
            'series' => 'FILTER_MODAL_SERIES_TOKEN',
            'notes' => 'FILTER_MODAL_OTHER_NOTES_TOKEN',
            'num_re_listen_times' => 3,
            're_listen_value' => 4,
        ]);

        $wrongReListen = Product::factory()->create([
            'work_name' => 'FILTER_MODAL_WRONG_RELISTEN_TOKEN',
            'score' => 8,
            'priority' => 2,
            'series' => 'FILTER_MODAL_SERIES_TOKEN',
            'notes' => 'FILTER_MODAL_NOTES_TOKEN',
            'num_re_listen_times' => 1,
            're_listen_value' => 2,
        ]);

        $this->get('/?title=filter_modal_match_token&series=FILTER_MODAL_SERIES_TOKEN&score=8&priority=2&notes=filter_modal_notes_token&num_re_listen_times=3&re_listen_value=4')
            ->assertOk()
            ->assertSee($matching->work_name)
            ->assertDontSee($wrongPriority->work_name)
            ->assertDontSee($wrongScore->work_name)
            ->assertDontSee($wrongNotes->work_name)
            ->assertDontSee($wrongReListen->work_name);
    }

    public function test_index_filter_modal_renders_extended_fields(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-index-filter-open', false)
            ->assertSee('data-index-filter-modal', false)
            ->assertSee('scripts/index-advanced-filters.js', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="notes"', false)
            ->assertSee('name="score"', false)
            ->assertSee('name="priority"', false)
            ->assertSee('name="series"', false)
            ->assertSee('name="num_re_listen_times"', false)
            ->assertSee('name="re_listen_value"', false)
            ->assertSee('name="tags"', false)
            ->assertSee('name="sort_first_field"', false);
    }

    public function test_index_filter_modal_defaults_to_all_tags_and_desc_sort_direction(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                'name="tag_match" value="all"',
                'name="tag_match" value="any"',
            ], false)
            ->assertSee('name="tag_match" value="all"', false)
            ->assertDontSee('name="tag_match" value="any" checked', false)
            ->assertSee('name="sort_first_direction" value="desc"', false)
            ->assertDontSee('name="sort_first_direction" value="asc" checked', false)
            ->assertSee('name="sort_second_direction" value="desc"', false)
            ->assertDontSee('name="sort_second_direction" value="asc" checked', false);
    }

    public function test_index_filters_by_priority_exactly_including_zero(): void
    {
        $lowPriority = Product::factory()->create([
            'work_name' => 'PRIORITY_LOW_TOKEN',
            'priority' => 0,
        ]);

        $mediumPriority = Product::factory()->create([
            'work_name' => 'PRIORITY_MEDIUM_TOKEN',
            'priority' => 1,
        ]);

        $highPriority = Product::factory()->create([
            'work_name' => 'PRIORITY_HIGH_TOKEN',
            'priority' => 2,
        ]);

        $noPriority = Product::factory()->create([
            'work_name' => 'PRIORITY_NONE_TOKEN',
            'priority' => null,
        ]);

        $this->get('/?priority=0')
            ->assertOk()
            ->assertSee($lowPriority->work_name)
            ->assertDontSee($mediumPriority->work_name)
            ->assertDontSee($highPriority->work_name)
            ->assertDontSee($noPriority->work_name);

        $this->get('/?priority=2')
            ->assertOk()
            ->assertSee($highPriority->work_name)
            ->assertDontSee($lowPriority->work_name)
            ->assertDontSee($mediumPriority->work_name)
            ->assertDontSee($noPriority->work_name);
    }

    public function test_index_filters_by_score_exactly(): void
    {
        $scoreEight = Product::factory()->create([
            'work_name' => 'SCORE_EIGHT_TOKEN',
            'score' => 8,
        ]);

        $scoreSeven = Product::factory()->create([
            'work_name' => 'SCORE_SEVEN_TOKEN',
            'score' => 7,
        ]);

        $scoreNull = Product::factory()->create([
            'work_name' => 'SCORE_NULL_TOKEN',
            'score' => null,
        ]);

        $this->get('/?score=8')
            ->assertOk()
            ->assertSee($scoreEight->work_name)
            ->assertDontSee($scoreSeven->work_name)
            ->assertDontSee($scoreNull->work_name);
    }

    public function test_index_filters_tags_by_any_or_all_using_csv_input_rules(): void
    {
        $firstTag = $this->createGenre('Junior / Senior (at work, school, etc)', Genre::TYPE_CUSTOM);
        $secondTag = $this->createGenre('Office Lady', Genre::TYPE_CUSTOM);
        $hiddenJapaneseTag = $this->createGenre('Hidden JP Filter Tag', Genre::TYPE_AUTO_GENERATED_JAPANESE);

        $matchingAll = Product::factory()->create([
            'work_name' => 'FILTER_TAGS_ALL_TOKEN',
        ]);
        $this->attachGenres($matchingAll, [$firstTag, $secondTag]);

        $matchingFirstOnly = Product::factory()->create([
            'work_name' => 'FILTER_TAGS_FIRST_TOKEN',
        ]);
        $this->attachGenres($matchingFirstOnly, [$firstTag]);

        $matchingSecondOnly = Product::factory()->create([
            'work_name' => 'FILTER_TAGS_SECOND_TOKEN',
        ]);
        $this->attachGenres($matchingSecondOnly, [$secondTag]);

        $hiddenJapaneseOnly = Product::factory()->create([
            'work_name' => 'FILTER_TAGS_HIDDEN_JP_TOKEN',
        ]);
        $this->attachGenres($hiddenJapaneseOnly, [$hiddenJapaneseTag]);

        $tagsQuery = rawurlencode('"Junior / Senior (at work, school, etc)", Office Lady');

        $this->get("/?tags={$tagsQuery}&tag_match=any")
            ->assertOk()
            ->assertSee($matchingAll->work_name)
            ->assertSee($matchingFirstOnly->work_name)
            ->assertSee($matchingSecondOnly->work_name)
            ->assertDontSee($hiddenJapaneseOnly->work_name);

        $this->get("/?tags={$tagsQuery}&tag_match=all")
            ->assertOk()
            ->assertSee($matchingAll->work_name)
            ->assertDontSee($matchingFirstOnly->work_name)
            ->assertDontSee($matchingSecondOnly->work_name)
            ->assertDontSee($hiddenJapaneseOnly->work_name);

        $hiddenTagsQuery = rawurlencode('Hidden JP Filter Tag');

        $this->get("/?tags={$hiddenTagsQuery}&tag_match=any")
            ->assertOk()
            ->assertDontSee($hiddenJapaneseOnly->work_name);
    }

    public function test_index_sorts_by_start_and_finish_date_with_primary_and_secondary_server_side_sort(): void
    {
        $beta = Product::factory()->create([
            'work_name' => 'SORT_DATE_BETA_TOKEN',
            'start_date' => ['year' => '2024', 'month' => '01', 'day' => '01'],
            'end_date' => ['year' => '2024', 'month' => '01', 'day' => '05'],
        ]);

        $alpha = Product::factory()->create([
            'work_name' => 'SORT_DATE_ALPHA_TOKEN',
            'start_date' => ['year' => '2024', 'month' => '01', 'day' => '01'],
            'end_date' => ['year' => '2024', 'month' => '01', 'day' => '03'],
        ]);

        $gamma = Product::factory()->create([
            'work_name' => 'SORT_DATE_GAMMA_TOKEN',
            'start_date' => ['year' => '2023', 'month' => '12', 'day' => '31'],
            'end_date' => ['year' => '2024', 'month' => '02', 'day' => '01'],
        ]);

        $this->get('/?sort_first_field=start_date&sort_first_direction=asc&sort_second_field=end_date&sort_second_direction=desc')
            ->assertOk()
            ->assertSeeInOrder([
                $gamma->work_name,
                $beta->work_name,
                $alpha->work_name,
            ]);
    }

    public function test_index_sorts_by_re_listen_fields_with_primary_and_secondary_server_side_sort(): void
    {
        $beta = Product::factory()->create([
            'work_name' => 'SORT_RELISTEN_BETA_TOKEN',
            'num_re_listen_times' => 3,
            're_listen_value' => 5,
        ]);

        $alpha = Product::factory()->create([
            'work_name' => 'SORT_RELISTEN_ALPHA_TOKEN',
            'num_re_listen_times' => 3,
            're_listen_value' => 2,
        ]);

        $gamma = Product::factory()->create([
            'work_name' => 'SORT_RELISTEN_GAMMA_TOKEN',
            'num_re_listen_times' => 1,
            're_listen_value' => 4,
        ]);

        $this->get('/?sort_first_field=num_re_listen_times&sort_first_direction=asc&sort_second_field=re_listen_value&sort_second_direction=desc')
            ->assertOk()
            ->assertSeeInOrder([
                $gamma->work_name,
                $beta->work_name,
                $alpha->work_name,
            ]);
    }

    public function test_index_shows_empty_state_when_filters_match_nothing(): void
    {
        Product::factory()->create([
            'work_name' => 'EMPTY_STATE_EXISTING_TOKEN',
        ]);

        $this->get('/?title=DOES_NOT_EXIST_TOKEN')
            ->assertOk()
            ->assertSee('Nothing found for the current filters.')
            ->assertDontSee('EMPTY_STATE_EXISTING_TOKEN');
    }

    public function test_tag_library_lists_clickable_english_and_custom_genres(): void
    {
        $englishGenre = $this->createGenre('Library English Tag', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $customGenre = $this->createGenre('Library Custom Tag', Genre::TYPE_CUSTOM);
        $this->createGenre('Library Japanese Tag', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $sharedLanguageGenre = $this->createGenre('Library Shared Language Tag', Genre::TYPE_AUTO_GENERATED_ENGLISH);

        $firstProduct = Product::factory()->create([
            'work_name' => 'LIBRARY_TAG_COUNT_FIRST_TOKEN',
        ]);
        $secondProduct = Product::factory()->create([
            'work_name' => 'LIBRARY_TAG_COUNT_SECOND_TOKEN',
        ]);
        $japaneseOnlyProduct = Product::factory()->create([
            'work_name' => 'LIBRARY_TAG_COUNT_JP_ONLY_TOKEN',
        ]);

        $this->attachGenres($firstProduct, [$englishGenre, $customGenre, $sharedLanguageGenre]);
        $this->attachGenres($secondProduct, [$englishGenre]);
        $sharedLanguageGenre->setAttribute('type', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $this->attachGenres($japaneseOnlyProduct, [$sharedLanguageGenre]);

        $response = $this->from('/?progress=Listening')->get('/tags');

        $response->assertOk()
            ->assertSee('Tag Library')
            ->assertSee('Quick Add')
            ->assertSee('Library English Tag (2)')
            ->assertSee('Library Custom Tag (1)')
            ->assertSee('Library Shared Language Tag (1)')
            ->assertDontSee('Library Japanese Tag')
            ->assertSee('data-list-menu-toggle', false)
            ->assertSee('data-list-menu-overlay', false)
            ->assertSee('genre=' . $englishGenre->getKey(), false)
            ->assertSee('genre=' . $customGenre->getKey(), false)
            ->assertSee('href="/create"', false)
            ->assertDontSee('hero__back', false);

        $this->get('/?genre=' . $sharedLanguageGenre->getKey())
            ->assertOk()
            ->assertSee($firstProduct->work_name)
            ->assertDontSee($japaneseOnlyProduct->work_name);
    }

    public function test_create_renders_form_page(): void
    {
        $this->get('/create')
            ->assertOk()
            ->assertSee('Add Work')
            ->assertSee('DLSite Create')
            ->assertSee('Custom Create')
            ->assertSee('width=device-width, initial-scale=1', false)
            ->assertSee('Custom Tags')
            ->assertSee('name="id"', false)
            ->assertDontSee('name="return_route"', false)
            ->assertSee('href="http://localhost"', false)
            ->assertSee('id="add_start_date_month"', false)
            ->assertSee('id="add_finish_date_month"', false)
            ->assertDontSee('name="age_category"', false)
            ->assertDontSee('name="work_image"', false)
            ->assertDontSee('name="sample_images[]"', false);
    }

    public function test_custom_create_renders_form_page(): void
    {
        $this->get('/create/custom')
            ->assertOk()
            ->assertSee('Add Custom Work')
            ->assertSee('DLSite Create')
            ->assertSee('Custom Create')
            ->assertSee('/store/custom', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="id"', false)
            ->assertSee('name="work_name"', false)
            ->assertSee('name="age_category"', false)
            ->assertSee('name="work_image"', false)
            ->assertSee('name="sample_images[]"', false)
            ->assertSee('file-upload-input', false)
            ->assertSee('Select age category');
    }

    public function test_create_preserves_index_return_state_for_go_back_and_mode_switch(): void
    {
        $query = http_build_query([
            'return_query' => [
                'progress' => 'Listening',
                'search' => 'rain',
                'page' => '3',
            ],
        ]);

        $this->get("/create?{$query}")
            ->assertOk()
            ->assertDontSee('name="return_route"', false)
            ->assertSeeInOrder(['name="return_query[search]"', 'value="rain"'], false)
            ->assertSeeInOrder(['name="return_query[progress]"', 'value="Listening"'], false)
            ->assertSeeInOrder(['name="return_query[page]"', 'value="3"'], false)
            ->assertSee('name="return_url" value="http://localhost"', false)
            ->assertSee('href="http://localhost"', false)
            ->assertSee('href="/create/custom?return_url=http%3A%2F%2Flocalhost&amp;return_query%5Bsearch%5D=rain&amp;return_query%5Bprogress%5D=Listening&amp;return_query%5Bpage%5D=3"', false);
    }

    public function test_create_go_back_uses_previous_url_with_index_fallback(): void
    {
        $this->from('/tags')->get('/create')
            ->assertOk()
            ->assertDontSee('name="return_route"', false)
            ->assertDontSee('name="return_query[search]"', false)
            ->assertDontSee('name="return_query[progress]"', false)
            ->assertSee('href="http://localhost/tags"', false)
            ->assertSee('href="/create/custom?return_url=http%3A%2F%2Flocalhost%2Ftags"', false);
    }

    public function test_create_go_back_ignores_malformed_return_url_input(): void
    {
        $query = http_build_query([
            'return_url' => [
                'bad' => 'http://localhost/create',
            ],
            'return_query' => [
                'progress' => 'Listening',
            ],
        ]);

        $this->from('/tags')->get("/create?{$query}")
            ->assertOk()
            ->assertSee('name="return_url" value="http://localhost/tags"', false)
            ->assertSee('href="http://localhost/tags"', false)
            ->assertSee('href="/create/custom?return_url=http%3A%2F%2Flocalhost%2Ftags&amp;return_query%5Bprogress%5D=Listening"', false)
            ->assertDontSee('return_url%5Bbad%5D', false);
    }

    public function test_create_mode_switch_preserves_original_go_back_url(): void
    {
        $query = http_build_query([
            'return_url' => 'http://localhost/tags',
            'return_query' => [
                'progress' => 'Listening',
            ],
        ]);

        $this->from('/create')->get("/create/custom?{$query}")
            ->assertOk()
            ->assertSee('name="return_url" value="http://localhost/tags"', false)
            ->assertSee('href="http://localhost/tags"', false)
            ->assertSee('href="/create?return_url=http%3A%2F%2Flocalhost%2Ftags&amp;return_query%5Bprogress%5D=Listening"', false)
            ->assertDontSee('href="http://localhost/create"', false);
    }

    public function test_create_preserves_go_back_target_after_scraper_validation_error(): void
    {
        Process::fake([
            '*' => Process::result(
                errorOutput: 'Deleted or Non-existing DLSite work',
                exitCode: 2,
            ),
        ])->preventStrayProcesses();

        $this->followingRedirects()
            ->from('/create')
            ->post('/store', [
                'id' => 'RJ000000404',
                'return_url' => 'http://localhost/?progress=Listening',
                'return_query' => [
                    'progress' => 'Listening',
                    'search' => 'rain',
                ],
            ])
            ->assertOk()
            ->assertSee('Deleted or Non-existing DLSite work')
            ->assertSee('name="return_url" value="http://localhost/?progress=Listening"', false)
            ->assertSeeInOrder(['name="return_query[search]"', 'value="rain"'], false)
            ->assertSeeInOrder(['name="return_query[progress]"', 'value="Listening"'], false)
            ->assertSee('href="http://localhost/?progress=Listening"', false)
            ->assertDontSee('href="http://localhost/create"', false);
    }

    public function test_create_go_back_uses_laravel_previous_url_for_external_referrer(): void
    {
        $this->withHeader('referer', 'https://example.com/tags')
            ->get('/create')
            ->assertOk()
            ->assertSee('href="https://example.com/tags"', false);
    }

    public function test_create_renders_enum_backed_select_labels(): void
    {
        $response = $this->get('/create');

        $response->assertOk();

        foreach (ProductProgress::options() as $value => $label) {
            $response->assertSee('value="' . e($value) . '"', false);
            $response->assertSee($label);
        }

        foreach (ProductScore::options() as $value => $label) {
            $response->assertSee('value="' . e($value) . '"', false);
            $response->assertSee($label);
        }

        foreach (ProductPriority::options() as $value => $label) {
            $response->assertSee('value="' . e($value) . '"', false);
            $response->assertSee($label);
        }

        foreach (ProductReListenValue::options() as $value => $label) {
            $response->assertSee('value="' . e($value) . '"', false);
            $response->assertSee($label);
        }
    }

    public function test_edit_renders_form_page_for_existing_product(): void
    {
        $japaneseGenre = $this->createGenre('JP_ONLY_GUIDANCE_TOKEN', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $englishGenre = $this->createGenre('Sleep Guidance EN', Genre::TYPE_AUTO_GENERATED_ENGLISH);

        $product = Product::factory()->create([
            'work_name' => 'EDIT_VIEW_NAME_TOKEN',
            'work_name_english' => 'EDIT_VIEW_EN_TOKEN',
            'notes' => 'EDIT_VIEW_NOTES_TOKEN',
        ]);
        $this->attachGenres($product, [$japaneseGenre, $englishGenre]);

        $query = http_build_query([
            'return_query' => [
                'progress' => 'Listening',
            ],
            'return_fragment' => $product->id,
        ]);

        $this->get("/edit/{$product->id}?{$query}")
            ->assertOk()
            ->assertSee('Edit Work')
            ->assertSee('width=device-width, initial-scale=1', false)
            ->assertSee($product->id)
            ->assertSee($product->work_name)
            ->assertSee($product->work_name_english)
            ->assertSee('Fetched EN Genres')
            ->assertSee('Sleep Guidance EN')
            ->assertDontSee('JP_ONLY_GUIDANCE_TOKEN')
            ->assertSee($product->notes)
            ->assertDontSee('name="return_route"', false)
            ->assertSee('name="return_query[progress]"', false)
            ->assertSee('name="return_fragment"', false)
            ->assertSee('href="/?progress=Listening#' . $product->id . '"', false)
            ->assertDontSee('name="redirect"', false);
    }

    public function test_edit_defaults_go_back_fragment_to_current_product_when_not_provided(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'EDIT_DEFAULT_FRAGMENT_TOKEN',
        ]);

        $this->get("/edit/{$product->id}?return_query[progress]=Listening")
            ->assertOk()
            ->assertSee('name="return_fragment" value="' . $product->id . '"', false)
            ->assertSee('href="/?progress=Listening#' . $product->id . '"', false);
    }

    public function test_edit_prefills_comma_custom_tags_as_quoted_csv(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'EDIT_TAG_PREFILL_TOKEN',
        ]);
        $this->attachGenres($product, [
            $this->createGenre('Junior / Senior (at work, school, etc)', Genre::TYPE_CUSTOM),
            $this->createGenre('Office Lady', Genre::TYPE_CUSTOM),
        ]);

        $this->get("/edit/{$product->id}")
            ->assertOk()
            ->assertSee('name="genre_custom"', false)
            ->assertSee('"Junior / Senior (at work, school, etc)", Office Lady');
    }

    public function test_edit_keeps_fetched_english_tags_readonly_when_option_disabled(): void
    {
        $englishGenre = $this->createGenre('READONLY_FETCHED_EN_TOKEN', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $product = Product::factory()->create([
            'work_name' => 'READONLY_FETCHED_EDIT_TOKEN',
        ]);
        $this->attachGenres($product, [$englishGenre]);

        $this->get("/edit/{$product->id}")
            ->assertOk()
            ->assertSee('Fetched EN Genres')
            ->assertSee('READONLY_FETCHED_EN_TOKEN')
            ->assertSee('readonly', false)
            ->assertDontSee('name="genre_fetched_english"', false);
    }

    public function test_edit_renders_editable_fetched_english_tags_when_option_enabled(): void
    {
        Option::setCanEditFetchedTags(true);

        $englishGenre = $this->createGenre('EDITABLE_FETCHED_EN_TOKEN', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $product = Product::factory()->create([
            'work_name' => 'EDITABLE_FETCHED_EDIT_TOKEN',
        ]);
        $this->attachGenres($product, [$englishGenre]);

        $this->get("/edit/{$product->id}")
            ->assertOk()
            ->assertSee('name="genre_fetched_english"', false)
            ->assertSee('EDITABLE_FETCHED_EN_TOKEN')
            ->assertDontSee('No fetched genres.');
    }

    public function test_update_ignores_fetched_english_input_when_option_disabled(): void
    {
        $englishGenre = $this->createGenre('UNCHANGED_FETCHED_EN_TOKEN', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $product = Product::factory()->create([
            'work_name' => 'IGNORE_FETCHED_EDIT_TOKEN',
        ]);
        $this->attachGenres($product, [$englishGenre]);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'genre_fetched_english' => 'MALICIOUS_FETCHED_EN_TOKEN',
        ])->assertSessionHasNoErrors();

        $product->refresh()->load('englishGenres');
        $this->assertSame(['UNCHANGED_FETCHED_EN_TOKEN'], $product->englishGenres->pluck('title')->all());
        $this->assertDatabaseMissing('genres', [
            'title' => 'MALICIOUS_FETCHED_EN_TOKEN',
        ]);
    }

    public function test_update_can_replace_fetched_english_tags_when_option_enabled(): void
    {
        Option::setCanEditFetchedTags(true);

        $product = Product::factory()->create([
            'work_name' => 'REPLACE_FETCHED_EDIT_TOKEN',
        ]);
        $oldEnglishGenre = Genre::query()->create(['title' => 'Old Editable EN']);
        $keptEnglishGenre = Genre::query()->create(['title' => 'Kept Editable EN']);
        Genre::query()->create(['title' => 'New Editable EN']);

        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_ENGLISH => [
                $oldEnglishGenre->getKey(),
                $keptEnglishGenre->getKey(),
            ],
        ], []);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'genre_fetched_english' => 'Kept Editable EN, New Editable EN',
            'genre_custom' => 'Custom Editable Tag',
        ])->assertSessionHasNoErrors();

        $product->refresh()->load(['englishGenres', 'customGenres']);
        $this->assertEqualsCanonicalizing(
            ['Kept Editable EN', 'New Editable EN'],
            $product->englishGenres->pluck('title')->all()
        );
        $this->assertSame(['Custom Editable Tag'], $product->customGenres->pluck('title')->all());
        $this->assertDatabaseMissing('genre_product', [
            'product_id' => $product->getKey(),
            'genre_id' => $oldEnglishGenre->getKey(),
        ]);
    }

    public function test_update_preserves_japanese_fetched_tags_when_editing_fetched_english_tags(): void
    {
        Option::setCanEditFetchedTags(true);

        $product = Product::factory()->create([
            'work_name' => 'PRESERVE_JP_FETCHED_EDIT_TOKEN',
        ]);
        $japaneseGenre = Genre::query()->create(['title' => 'Hidden JP Editable']);
        $oldEnglishGenre = Genre::query()->create(['title' => 'Old Visible EN']);
        Genre::query()->create(['title' => 'Replacement Visible EN']);

        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_JAPANESE => [$japaneseGenre->getKey()],
            Genre::LANGUAGE_ENGLISH => [$oldEnglishGenre->getKey()],
        ], []);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'genre_fetched_english' => 'Replacement Visible EN',
        ])->assertSessionHasNoErrors();

        $product->refresh()->load(['japaneseGenres', 'englishGenres']);
        $this->assertSame(['Hidden JP Editable'], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['Replacement Visible EN'], $product->englishGenres->pluck('title')->all());
        $this->assertDatabaseMissing('genre_product', [
            'product_id' => $product->getKey(),
            'genre_id' => $oldEnglishGenre->getKey(),
        ]);
    }

    public function test_store_rejects_invalid_rj_code(): void
    {
        $response = $this->from('/create')->post('/store', [
            'id' => 'not-an-rj-code',
        ]);

        $response->assertRedirect('/create');
        $response->assertSessionHasErrors(['id']);
    }

    public function test_store_rejects_duplicate_rj_code(): void
    {
        $existing = Product::factory()->create();

        $response = $this->from('/create')->post('/store', [
            'id' => $existing->id,
        ]);

        $response->assertRedirect('/create');
        $response->assertSessionHasErrors(['id']);
    }

    public function test_store_extracts_rj_from_url_before_validation(): void
    {
        $existing = Product::factory()->create();
        $urlInput = 'https://www.dlsite.com/maniax/work/=/product_id/' . strtolower($existing->id) . '.html';

        $response = $this->from('/create')->post('/store', [
            'id' => $urlInput,
        ]);

        $response->assertRedirect('/create');
        $response->assertSessionHasErrors(['id']);

        $this->assertSame('This RJ work is already in the database', session('errors')->first('id'));
    }

    public function test_store_uses_fake_dlsite_process_and_scraped_json_to_create_product(): void
    {
        Storage::fake('local');
        Process::fake([
            '*' => Process::result(),
        ])->preventStrayProcesses();

        $workId = Product::factory()->make()->id;

        Storage::disk('local')->put("Works/{$workId}.json", json_encode([
            'japanese' => [
                'product_id' => $workId,
                'maker_id' => 'RG12345',
                'work_name' => 'SCRAPED_JP_TITLE_TOKEN',
                'age_category' => ['_name_' => 'R18'],
                'circle' => 'SCRAPED_CIRCLE_TOKEN',
                'sample_images' => [],
                'genre' => ['Scraped JP Tag', 'ASMR'],
                'description' => 'SCRAPED_JP_DESCRIPTION_TOKEN',
            ],
            'english' => [
                'work_name' => 'SCRAPED_EN_TITLE_TOKEN',
                'genre' => ['Scraped EN Tag', 'ASMR'],
                'description' => 'SCRAPED_EN_DESCRIPTION_TOKEN',
            ],
        ]));

        $response = $this->post('/store', [
            'id' => strtolower($workId),
            'progress' => 'Completed',
            'genre_custom' => 'Scraped Custom Tag',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/#{$workId}");

        Process::assertRan(function ($process) use ($workId): bool {
            return $process->command === [
                $this->expectedPythonExecutable(),
                base_path('python/DLSiteScraper.py'),
                storage_path(),
                $workId,
            ] && $process->timeout === null;
        });

        $product = Product::query()->whereKey($workId)->firstOrFail();
        $product->load(['japaneseGenres', 'englishGenres', 'customGenres']);

        $this->assertSame('SCRAPED_JP_TITLE_TOKEN', $product->work_name);
        $this->assertSame('SCRAPED_EN_TITLE_TOKEN', $product->work_name_english);
        $this->assertSame('Completed', $product->progress);
        $this->assertEqualsCanonicalizing(['ASMR', 'Scraped JP Tag'], $product->japaneseGenres->pluck('title')->all());
        $this->assertEqualsCanonicalizing(['ASMR', 'Scraped EN Tag'], $product->englishGenres->pluck('title')->all());
        $this->assertSame(['Scraped Custom Tag'], $product->customGenres->pluck('title')->all());
        $this->assertSame(1, Genre::query()->where('title', 'ASMR')->count());

        $asmrGenre = Genre::query()->where('title', 'ASMR')->firstOrFail();
        $asmrPivotId = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('genre_id', $asmrGenre->getKey())
            ->value('id');

        $this->assertEqualsCanonicalizing(
            [Genre::LANGUAGE_JAPANESE, Genre::LANGUAGE_ENGLISH],
            DB::table('genre_product_languages')
                ->where('genre_product_id', $asmrPivotId)
                ->pluck('language')
                ->all()
        );
    }

    public function test_store_rejects_invalid_date_order(): void
    {
        $response = $this->from('/create')->post('/store', [
            'id' => Product::factory()->make()->id,
            'add' => [
                'start_date' => [
                    'month' => '02',
                    'day' => '10',
                    'year' => '2025',
                ],
                'finish_date' => [
                    'month' => '02',
                    'day' => '09',
                    'year' => '2025',
                ],
            ],
        ]);

        $response->assertRedirect('/create');
        $response->assertSessionHasErrors(['add.finish_date']);
    }

    public function test_store_rejects_impossible_date(): void
    {
        $response = $this->from('/create')->post('/store', [
            'id' => Product::factory()->make()->id,
            'add' => [
                'start_date' => [
                    'month' => '02',
                    'day' => '31',
                    'year' => '2025',
                ],
            ],
        ]);

        $response->assertRedirect('/create');
        $response->assertSessionHasErrors(['add.start_date']);
    }

    public function test_store_rejects_invalid_progress(): void
    {
        $response = $this->from('/create')->post('/store', [
            'id' => Product::factory()->make()->id,
            'progress' => 'Paused',
        ]);

        $response->assertRedirect('/create');
        $response->assertSessionHasErrors(['progress']);
    }

    public function test_store_rejects_invalid_score(): void
    {
        $response = $this->from('/create')->post('/store', [
            'id' => Product::factory()->make()->id,
            'score' => '999',
        ]);

        $response->assertRedirect('/create');
        $response->assertSessionHasErrors(['score']);
    }

    public function test_store_rejects_negative_num_re_listen_times(): void
    {
        $existing = Product::factory()->create();

        $response = $this->from('/create')->post('/store', [
            'id' => $existing->id,
            'add' => [
                'num_re_listen_times' => '-1',
            ],
        ]);

        $response->assertRedirect('/create');
        $response->assertSessionHasErrors(['num_re_listen_times']);
    }

    public function test_store_rejects_out_of_range_re_listen_value(): void
    {
        $existing = Product::factory()->create();

        foreach (['0', '6'] as $value) {
            $response = $this->from('/create')->post('/store', [
                'id' => $existing->id,
                'add' => [
                    're_listen_value' => $value,
                ],
            ]);

            $response->assertRedirect('/create');
            $response->assertSessionHasErrors(['re_listen_value']);
        }
    }

    public function test_store_rejects_out_of_range_priority(): void
    {
        $existing = Product::factory()->create();

        foreach (['-1', '3'] as $value) {
            $response = $this->from('/create')->post('/store', [
                'id' => $existing->id,
                'add' => [
                    'priority' => $value,
                ],
            ]);

            $response->assertRedirect('/create');
            $response->assertSessionHasErrors(['priority']);
        }
    }

    public function test_custom_store_saves_local_uploads_and_sets_work_image_path(): void
    {
        Storage::fake('public');

        $workId = Product::factory()->make()->id;
        $cover = UploadedFile::fake()->image('cover.png')->size(1024);
        $sampleOne = UploadedFile::fake()->image('sample-one.jpg')->size(1024);
        $sampleTwo = UploadedFile::fake()->image('sample-two.png')->size(1024);

        $response = $this->post('/store/custom', [
            'id' => strtolower($workId),
            'work_name' => 'CUSTOM_STORE_JP_TOKEN',
            'work_name_english' => 'CUSTOM_STORE_EN_TOKEN',
            'age_category' => 'R18',
            'progress' => 'Completed',
            'score' => 8,
            'series' => 'CUSTOM_STORE_SERIES_TOKEN',
            'genre_custom' => 'Custom One, Custom Two',
            'notes' => 'CUSTOM_STORE_NOTES_TOKEN',
            'work_image' => $cover,
            'sample_images' => [$sampleOne, $sampleTwo],
            'add' => [
                'start_date' => [
                    'month' => '04',
                    'day' => '01',
                    'year' => '2026',
                ],
                'finish_date' => [
                    'month' => '04',
                    'day' => '02',
                    'year' => '2026',
                ],
                'num_re_listen_times' => '2',
                're_listen_value' => '4',
                'priority' => '1',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/#' . $workId);

        Storage::disk('public')->assertExists("Works/{$workId}/cover.png");
        Storage::disk('public')->assertExists("Works/{$workId}/sample_1.jpg");
        Storage::disk('public')->assertExists("Works/{$workId}/sample_2.png");

        $product = Product::query()->whereKey($workId)->firstOrFail();

        $this->assertSame("storage/Works/{$workId}/cover.png", $product->work_image);
        $this->assertSame('CUSTOM_STORE_JP_TOKEN', $product->work_name);
        $this->assertSame('CUSTOM_STORE_EN_TOKEN', $product->work_name_english);
        $this->assertSame('R18', $product->age_category);
        $this->assertSame('CUSTOM_STORE_SERIES_TOKEN', $product->series);
        $this->assertSame('CUSTOM_STORE_NOTES_TOKEN', $product->notes);
        $this->assertSame(
            [
                "storage/Works/{$workId}/sample_1.jpg",
                "storage/Works/{$workId}/sample_2.png",
            ],
            json_decode($product->sample_images, true)
        );
        $this->assertEqualsCanonicalizing(
            ['Custom One', 'Custom Two'],
            $product->customGenres->pluck('title')->all()
        );

        $this->get('/')
            ->assertOk()
            ->assertSee('src="storage/Works/' . $workId . '/cover.png"', false)
            ->assertDontSee('images/No Image.png', false);
    }

    public function test_custom_store_rejects_missing_cover_image(): void
    {
        Storage::fake('public');

        $workId = Product::factory()->make()->id;

        $response = $this->from('/create/custom')->post('/store/custom', [
            'id' => $workId,
            'work_name' => 'CUSTOM_NO_COVER_TOKEN',
            'age_category' => 'ALL_AGES',
        ]);

        $response->assertRedirect('/create/custom');
        $response->assertSessionHasErrors(['work_image']);
        $this->assertDatabaseMissing('products', ['id' => $workId]);
    }

    public function test_index_image_uses_stored_work_image_path(): void
    {
        Product::factory()->create([
            'work_name' => 'IMAGE_PRIMARY_TOKEN',
            'work_image' => 'storage/Works/PRIMARY/cover.webp',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('IMAGE_PRIMARY_TOKEN')
            ->assertSee('src="storage/Works/PRIMARY/cover.webp"', false)
            ->assertDontSee('images/No Image.png', false);
    }

    public function test_custom_store_keeps_matching_existing_genre_editable_as_custom_tag(): void
    {
        Storage::fake('public');

        $existingGenre = $this->createGenre('Existing Store Auto Genre', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $workId = Product::factory()->make()->id;

        $response = $this->post('/store/custom', [
            'id' => $workId,
            'work_name' => 'CUSTOM_EXISTING_GENRE_TOKEN',
            'age_category' => 'ALL_AGES',
            'genre_custom' => 'Existing Store Auto Genre',
            'work_image' => UploadedFile::fake()->image('cover.png')->size(1024),
        ]);

        $response->assertSessionHasNoErrors();

        $product = Product::query()->whereKey($workId)->firstOrFail();
        $product->load(['genres', 'englishGenres', 'customGenres']);

        $this->assertSame([$existingGenre->getKey()], $product->genres->pluck('id')->all());
        $this->assertSame([], $product->englishGenres->pluck('title')->all());
        $this->assertSame(['Existing Store Auto Genre'], $product->customGenres->pluck('title')->all());

        $this->get("/edit/{$product->id}")
            ->assertOk()
            ->assertSee('No fetched genres.')
            ->assertSee('Existing Store Auto Genre');
    }

    public function test_custom_store_returns_to_new_work_on_calculated_index_page(): void
    {
        Storage::fake('public');
        Option::setIndexPerPage(2);

        Product::factory()->create(['id' => 'RJ000000005', 'progress' => 'Listening']);
        Product::factory()->create(['id' => 'RJ000000004', 'progress' => 'Listening']);
        Product::factory()->create(['id' => 'RJ000000002', 'progress' => 'Listening']);
        Product::factory()->create(['id' => 'RJ000000001', 'progress' => 'Listening']);

        $workId = 'RJ000000003';

        $response = $this->post('/store/custom', $this->customStorePayload($workId, [
            'progress' => 'Listening',
            'return_query' => [
                'progress' => 'Listening',
                'page' => '9',
            ],
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/?progress=Listening&page=2#{$workId}");
    }

    public function test_custom_store_returns_to_new_work_on_calculated_custom_sort_page(): void
    {
        Storage::fake('public');
        Option::setIndexPerPage(2);

        Product::factory()->create(['id' => 'RJ000000001', 'score' => 1]);
        Product::factory()->create(['id' => 'RJ000000002', 'score' => 3]);
        Product::factory()->create(['id' => 'RJ000000004', 'score' => 7]);
        Product::factory()->create(['id' => 'RJ000000005', 'score' => 9]);

        $workId = 'RJ000000003';

        $response = $this->post('/store/custom', $this->customStorePayload($workId, [
            'score' => 5,
            'return_query' => [
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
                'page' => '9',
            ],
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/?sort_first_field=score&sort_first_direction=asc&page=2#{$workId}");
    }

    public function test_custom_store_from_non_index_quick_add_returns_to_new_work_on_index(): void
    {
        Storage::fake('public');

        $workId = Product::factory()->make()->id;

        $response = $this->post('/store/custom', $this->customStorePayload($workId, [
            'return_route' => 'tags.index',
            'return_query' => [
                'search' => 'rain',
            ],
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/#{$workId}");
    }

    public function test_custom_store_preserves_matching_tag_filter_for_new_work(): void
    {
        Storage::fake('public');

        $workId = Product::factory()->make()->id;

        $response = $this->post('/store/custom', $this->customStorePayload($workId, [
            'genre_custom' => 'VisibleTag',
            'return_query' => [
                'tags' => 'VisibleTag',
                'tag_match' => 'all',
            ],
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/?tags=VisibleTag&tag_match=all#{$workId}");
    }

    public function test_custom_store_drops_hiding_tag_filter_for_new_work(): void
    {
        Storage::fake('public');

        $workId = Product::factory()->make()->id;

        $response = $this->post('/store/custom', $this->customStorePayload($workId, [
            'genre_custom' => 'VisibleTag',
            'return_query' => [
                'tags' => 'HiddenTag',
                'tag_match' => 'all',
            ],
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/#{$workId}");
    }

    public function test_custom_store_rejects_missing_required_fields(): void
    {
        $response = $this->from('/create/custom')->post('/store/custom', [
            'id' => Product::factory()->make()->id,
        ]);

        $response->assertRedirect('/create/custom');
        $response->assertSessionHasErrors(['work_name', 'age_category', 'work_image']);
    }

    public function test_custom_store_rejects_duplicate_rj_code(): void
    {
        Storage::fake('public');

        $existing = Product::factory()->create();

        $response = $this->from('/create/custom')->post('/store/custom', [
            'id' => $existing->id,
            'work_name' => 'CUSTOM_DUPLICATE_TOKEN',
            'age_category' => 'ALL_AGES',
            'work_image' => UploadedFile::fake()->image('cover.png')->size(1024),
        ]);

        $response->assertRedirect('/create/custom');
        $response->assertSessionHasErrors(['id']);
    }

    public function test_custom_store_rejects_invalid_age_category(): void
    {
        Storage::fake('public');

        $response = $this->from('/create/custom')->post('/store/custom', [
            'id' => Product::factory()->make()->id,
            'work_name' => 'CUSTOM_INVALID_AGE_TOKEN',
            'age_category' => 'NOT_VALID',
            'work_image' => UploadedFile::fake()->image('cover.png')->size(1024),
        ]);

        $response->assertRedirect('/create/custom');
        $response->assertSessionHasErrors(['age_category']);
    }

    public function test_custom_store_rejects_non_image_cover_upload(): void
    {
        Storage::fake('public');

        $response = $this->from('/create/custom')->post('/store/custom', [
            'id' => Product::factory()->make()->id,
            'work_name' => 'CUSTOM_INVALID_IMAGE_TOKEN',
            'age_category' => 'ALL_AGES',
            'work_image' => UploadedFile::fake()->create('cover.txt', 1, 'text/plain'),
        ]);

        $response->assertRedirect('/create/custom');
        $response->assertSessionHasErrors(['work_image']);
    }

    public function test_custom_store_rejects_oversized_sample_image_upload(): void
    {
        Storage::fake('public');

        $response = $this->from('/create/custom')->post('/store/custom', [
            'id' => Product::factory()->make()->id,
            'work_name' => 'CUSTOM_OVERSIZED_IMAGE_TOKEN',
            'age_category' => 'ALL_AGES',
            'work_image' => UploadedFile::fake()->image('cover.png')->size(1024),
            'sample_images' => [
                UploadedFile::fake()->image('sample.jpg')->size(20481),
            ],
        ]);

        $response->assertRedirect('/create/custom');
        $response->assertSessionHasErrors(['sample_images.0']);
    }

    public function test_update_requires_work_name(): void
    {
        $product = Product::factory()->create();

        $this->from("/edit/{$product->id}")
            ->post("/update/{$product->id}", [
                'progress' => 'Listening',
            ])
            ->assertRedirect("/edit/{$product->id}")
            ->assertSessionHasErrors(['work_name']);
    }

    public function test_update_saves_listening_fields(): void
    {
        $oldCustomGenre = $this->createGenre('OldTag', Genre::TYPE_CUSTOM);
        $existingEnglishGenre = $this->createGenre('Existing English Genre', Genre::TYPE_AUTO_GENERATED_ENGLISH);

        $product = Product::factory()->create([
            'work_name' => 'UPDATE_OLD_NAME_TOKEN',
            'work_name_english' => 'UPDATE_OLD_EN_TOKEN',
            'progress' => 'Plan to Listen',
            'score' => null,
            'series' => null,
            'notes' => null,
        ]);
        $this->attachGenres($product, [$oldCustomGenre, $existingEnglishGenre]);

        $response = $this->post("/update/{$product->id}", [
            'work_name' => 'UPDATE_NEW_NAME_TOKEN',
            'work_name_english' => 'UPDATE_NEW_EN_TOKEN',
            'progress' => 'Completed',
            'score' => 9,
            'series' => 'UPDATE_SERIES_TOKEN',
            'genre_custom' => 'Tag One, Tag Two',
            'notes' => "Line 1\nLine 2",
            'add' => [
                'start_date' => [
                    'month' => '03',
                    'day' => '01',
                    'year' => '2025',
                ],
                'finish_date' => [
                    'month' => '03',
                    'day' => '07',
                    'year' => '2025',
                ],
                'num_re_listen_times' => '3',
                're_listen_value' => '5',
                'priority' => '2',
            ],
            'return_route' => 'index',
            'return_query' => [
                'progress' => 'Plan to Listen',
            ],
            'return_fragment' => $product->id,
        ]);

        $response->assertSessionHasNoErrors();

        $product->refresh()->load(['customGenres', 'englishGenres']);

        $this->assertSame('UPDATE_NEW_NAME_TOKEN', $product->work_name);
        $this->assertSame('UPDATE_NEW_EN_TOKEN', $product->work_name_english);
        $this->assertSame('Completed', $product->progress);
        $this->assertSame(9, $product->score);
        $this->assertSame('UPDATE_SERIES_TOKEN', $product->series);
        $this->assertEqualsCanonicalizing(
            ['Tag One', 'Tag Two'],
            $product->customGenres->pluck('title')->all()
        );
        $this->assertSame(
            ['Existing English Genre'],
            $product->englishGenres->pluck('title')->all()
        );
        $this->assertSame("Line 1\nLine 2", $product->notes);
        $this->assertSame('03', (string) data_get($product->start_date, 'month'));
        $this->assertSame('01', (string) data_get($product->start_date, 'day'));
        $this->assertSame('2025', (string) data_get($product->start_date, 'year'));
        $this->assertSame('03', (string) data_get($product->end_date, 'month'));
        $this->assertSame('07', (string) data_get($product->end_date, 'day'));
        $this->assertSame('2025', (string) data_get($product->end_date, 'year'));
        $this->assertSame(3, $product->num_re_listen_times);
        $this->assertSame(5, $product->re_listen_value);
        $this->assertSame(2, $product->priority);
    }

    public function test_update_parses_quoted_custom_tag_with_commas_as_single_tag(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'QUOTED_TAGS_WORK_TOKEN',
        ]);
        $this->attachGenres($product, [
            $this->createGenre('Old Tag', Genre::TYPE_CUSTOM),
        ]);

        $response = $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => $product->progress,
            'genre_custom' => '"Junior / Senior (at work, school, etc)", Office Lady',
        ]);

        $response->assertSessionHasNoErrors();

        $product->refresh()->load('customGenres');

        $this->assertEqualsCanonicalizing(
            ['Junior / Senior (at work, school, etc)', 'Office Lady'],
            $product->customGenres->pluck('title')->all()
        );
    }

    public function test_update_keeps_matching_existing_genre_editable_when_added_as_custom_tag(): void
    {
        $existingGenre = $this->createGenre('Existing Auto Genre', Genre::TYPE_AUTO_GENERATED_ENGLISH);

        $product = Product::factory()->create([
            'work_name' => 'MATCH_EXISTING_GENRE_TOKEN',
        ]);

        $response = $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => $product->progress,
            'genre_custom' => 'Existing Auto Genre',
        ]);

        $response->assertSessionHasNoErrors();

        $product->refresh()->load(['genres', 'englishGenres', 'customGenres']);

        $this->assertSame(
            [$existingGenre->getKey()],
            $product->genres->pluck('id')->all()
        );
        $this->assertSame([], $product->englishGenres->pluck('title')->all());
        $this->assertSame(
            ['Existing Auto Genre'],
            $product->customGenres->pluck('title')->all()
        );
        $this->assertSame(1, Genre::query()->where('title', 'Existing Auto Genre')->count());

        $this->get("/edit/{$product->id}")
            ->assertOk()
            ->assertSee('No fetched genres.')
            ->assertSee('Existing Auto Genre');
    }

    public function test_update_redirect_rewrites_progress_when_value_changes(): void
    {
        $product = Product::factory()->create([
            'progress' => 'Listening',
            'work_name' => 'REDIRECT_CHANGED_TOKEN',
        ]);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => 'Completed',
            'return_route' => 'index',
            'return_query' => [
                'age_category' => 'ALL_AGES',
                'progress' => 'Listening',
            ],
            'return_fragment' => $product->id,
        ])->assertRedirect("/?age_category=ALL_AGES&progress=Completed#{$product->id}");
    }

    public function test_update_redirect_drops_search_when_it_would_hide_the_target_work(): void
    {
        $product = Product::factory()->create([
            'progress' => 'Listening',
            'work_name' => 'REDIRECT_SEARCH_DROPPED_TOKEN',
        ]);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => 'Completed',
            'return_query' => [
                'progress' => 'Listening',
                'search' => 'rain',
            ],
            'return_fragment' => $product->id,
        ])->assertRedirect("/?progress=Completed#{$product->id}");
    }

    public function test_update_redirect_keeps_progress_query_when_value_is_unchanged(): void
    {
        $product = Product::factory()->create([
            'progress' => 'Listening',
            'work_name' => 'REDIRECT_UNCHANGED_TOKEN',
        ]);

        $redirect = '/?age_category=ALL_AGES&progress=Listening';

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => 'Listening',
            'return_route' => 'index',
            'return_query' => [
                'age_category' => 'ALL_AGES',
                'progress' => 'Listening',
            ],
            'return_fragment' => $product->id,
        ])->assertRedirect("{$redirect}#{$product->id}");
    }

    public function test_update_redirect_drops_saved_page_when_progress_changes(): void
    {
        $product = Product::factory()->create([
            'progress' => 'Listening',
            'work_name' => 'REDIRECT_PAGE_DROP_TOKEN',
        ]);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => 'Completed',
            'return_route' => 'index',
            'return_query' => [
                'search' => 'rain',
                'progress' => 'Listening',
                'page' => '4',
            ],
            'return_fragment' => $product->id,
        ])->assertRedirect("/?progress=Completed#{$product->id}");
    }

    public function test_update_drops_filters_that_hide_the_target_work(): void
    {
        $product = Product::factory()->create([
            'progress' => 'Listening',
            'series' => 'VISIBLE_SERIES',
            'work_name' => 'VISIBLE_FILTER_TOKEN',
        ]);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => 'Listening',
            'series' => $product->series,
            'return_query' => [
                'progress' => 'Listening',
                'series' => 'HIDING_SERIES',
            ],
            'return_fragment' => $product->id,
        ])->assertRedirect("/?progress=Listening#{$product->id}");
    }

    public function test_update_preserves_matching_tag_filter_for_target_work(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'VISIBLE_TAG_FILTER_TOKEN',
        ]);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'genre_custom' => 'VisibleTag',
            'return_query' => [
                'tags' => 'VisibleTag',
                'tag_match' => 'all',
            ],
            'return_fragment' => $product->id,
        ])->assertRedirect("/?tags=VisibleTag&tag_match=all#{$product->id}");
    }

    public function test_update_preserves_matching_tag_filter_when_custom_tags_do_not_change(): void
    {
        $visibleTag = $this->createGenre('StableVisibleTag', Genre::TYPE_CUSTOM);
        $product = Product::factory()->create([
            'work_name' => 'STABLE_VISIBLE_TAG_FILTER_TOKEN',
        ]);
        $this->attachGenres($product, [$visibleTag]);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'work_name_english' => $product->work_name_english,
            'progress' => $product->progress,
            'genre_custom' => 'StableVisibleTag',
            'return_query' => [
                'tags' => 'StableVisibleTag',
                'tag_match' => 'all',
            ],
            'return_fragment' => $product->id,
        ])->assertRedirect("/?tags=StableVisibleTag&tag_match=all#{$product->id}");
    }

    public function test_update_drops_hiding_tag_filter_for_target_work(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'HIDDEN_TAG_FILTER_TOKEN',
        ]);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'genre_custom' => 'VisibleTag',
            'return_query' => [
                'tags' => 'HiddenTag',
                'tag_match' => 'all',
            ],
            'return_fragment' => $product->id,
        ])->assertRedirect("/#{$product->id}");
    }

    public function test_update_drops_tag_filter_when_custom_tag_is_removed(): void
    {
        $oldTag = $this->createGenre('RemovedVisibleTag', Genre::TYPE_CUSTOM);
        $product = Product::factory()->create([
            'work_name' => 'REMOVED_TAG_FILTER_TOKEN',
        ]);
        $this->attachGenres($product, [$oldTag]);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => $product->progress,
            'genre_custom' => 'ReplacementVisibleTag',
            'return_query' => [
                'tags' => 'RemovedVisibleTag',
                'tag_match' => 'all',
            ],
            'return_fragment' => $product->id,
        ])->assertRedirect("/#{$product->id}");
    }

    public function test_update_drops_each_hiding_visibility_filter_group(): void
    {
        $scenarios = [
            'search' => ['search' => 'HIDDEN_SEARCH_TOKEN'],
            'title' => ['title' => 'HIDDEN_TITLE_TOKEN'],
            'notes' => ['notes' => 'HIDDEN_NOTES_TOKEN'],
            'genre' => ['genre' => 'HiddenGenreToken'],
            'age_category' => ['age_category' => 'R18'],
            'score' => ['score' => '1'],
            'priority' => ['priority' => '0'],
            'num_re_listen_times' => ['num_re_listen_times' => '9'],
            're_listen_value' => ['re_listen_value' => '1'],
        ];

        foreach ($scenarios as $name => $hidingQuery) {
            $product = Product::factory()->create([
                'work_name' => "VISIBLE_FILTER_{$name}",
                'age_category' => 'ALL_AGES',
                'progress' => 'Listening',
            ]);

            $response = $this->post("/update/{$product->id}", [
                'work_name' => $product->work_name,
                'work_name_english' => "VISIBLE_ENGLISH_{$name}",
                'progress' => 'Listening',
                'score' => 8,
                'series' => "VISIBLE_SERIES_{$name}",
                'genre_custom' => "VisibleGenre{$name}",
                'notes' => "VISIBLE_NOTES_{$name}",
                'add' => [
                    'num_re_listen_times' => '3',
                    're_listen_value' => '4',
                    'priority' => '2',
                ],
                'return_query' => array_merge([
                    'progress' => 'Listening',
                ], $hidingQuery),
                'return_fragment' => $product->id,
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect("/?progress=Listening#{$product->id}");
        }
    }

    public function test_update_returns_to_target_work_on_calculated_custom_sort_page(): void
    {
        Option::setIndexPerPage(2);

        Product::factory()->create(['id' => 'RJ000000101', 'score' => 1]);
        Product::factory()->create(['id' => 'RJ000000102', 'score' => 3]);
        $product = Product::factory()->create([
            'id' => 'RJ000000103',
            'work_name' => 'CUSTOM_SORT_UPDATE_TARGET',
            'score' => 5,
        ]);
        Product::factory()->create(['id' => 'RJ000000104', 'score' => 7]);
        Product::factory()->create(['id' => 'RJ000000105', 'score' => 9]);

        $response = $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => $product->progress,
            'score' => 5,
            'return_query' => [
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
                'page' => '9',
            ],
            'return_fragment' => $product->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/?sort_first_field=score&sort_first_direction=asc&page=2#{$product->id}");
    }

    public function test_update_with_unchanged_visibility_fields_keeps_saved_page_and_filters(): void
    {
        Option::setIndexPerPage(2);

        Product::factory()->create([
            'id' => 'RJ000000101',
            'progress' => 'Listening',
            'series' => 'VISIBLE_SERIES',
            'score' => 1,
        ]);
        Product::factory()->create([
            'id' => 'RJ000000102',
            'progress' => 'Listening',
            'series' => 'VISIBLE_SERIES',
            'score' => 3,
        ]);
        $product = Product::factory()->create([
            'id' => 'RJ000000103',
            'work_name' => 'UNCHANGED_VISIBILITY_TARGET',
            'progress' => 'Listening',
            'series' => 'VISIBLE_SERIES',
            'score' => 5,
        ]);
        Product::factory()->create([
            'id' => 'RJ000000104',
            'progress' => 'Listening',
            'series' => 'VISIBLE_SERIES',
            'score' => 7,
        ]);

        $response = $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'work_name_english' => $product->work_name_english,
            'progress' => $product->progress,
            'score' => 5,
            'series' => $product->series,
            'add' => [
                'start_date' => [
                    'year' => '2025',
                    'month' => '03',
                    'day' => '01',
                ],
            ],
            'return_query' => [
                'progress' => 'Listening',
                'series' => 'VISIBLE_SERIES',
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
                'page' => '2',
            ],
            'return_fragment' => $product->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/?series=VISIBLE_SERIES&progress=Listening&sort_first_field=score&sort_first_direction=asc&page=2#{$product->id}");
    }

    public function test_update_workflow_returns_to_visible_work_after_filter_sort_and_page_changes(): void
    {
        Option::setIndexPerPage(2);

        Product::factory()->create([
            'id' => 'RJ000000101',
            'work_name' => 'WORKFLOW_LISTENING_SCORE_ONE',
            'progress' => 'Listening',
            'series' => 'OLD_WORKFLOW_SERIES',
            'score' => 1,
        ]);
        Product::factory()->create([
            'id' => 'RJ000000102',
            'work_name' => 'WORKFLOW_LISTENING_SCORE_THREE',
            'progress' => 'Listening',
            'series' => 'OLD_WORKFLOW_SERIES',
            'score' => 3,
        ]);
        Product::factory()->create(['id' => 'RJ000000201', 'progress' => 'Completed', 'score' => 1]);
        Product::factory()->create(['id' => 'RJ000000202', 'progress' => 'Completed', 'score' => 3]);
        Product::factory()->create(['id' => 'RJ000000204', 'progress' => 'Completed', 'score' => 7]);
        Product::factory()->create(['id' => 'RJ000000205', 'progress' => 'Completed', 'score' => 9]);

        $product = Product::factory()->create([
            'id' => 'RJ000000103',
            'work_name' => 'WORKFLOW_VISIBLE_UPDATE_TARGET',
            'progress' => 'Listening',
            'series' => 'OLD_WORKFLOW_SERIES',
            'score' => 5,
        ]);
        $returnQuery = [
            'progress' => 'Listening',
            'series' => 'OLD_WORKFLOW_SERIES',
            'sort_first_field' => 'score',
            'sort_first_direction' => 'asc',
            'page' => '2',
        ];

        $this->get('/?' . http_build_query($returnQuery))
            ->assertOk()
            ->assertSee($product->work_name)
            ->assertDontSee('WORKFLOW_LISTENING_SCORE_ONE');

        $this->get("/edit/{$product->id}?" . http_build_query([
            'return_query' => $returnQuery,
            'return_fragment' => $product->id,
        ]))
            ->assertOk()
            ->assertSeeInOrder(['name="return_query[series]"', 'value="OLD_WORKFLOW_SERIES"'], false)
            ->assertSeeInOrder(['name="return_query[sort_first_field]"', 'value="score"'], false)
            ->assertSeeInOrder(['name="return_query[page]"', 'value="2"'], false);

        $response = $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => 'Completed',
            'score' => 5,
            'series' => 'NEW_WORKFLOW_SERIES',
            'return_query' => $returnQuery,
            'return_fragment' => $product->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/?progress=Completed&sort_first_field=score&sort_first_direction=asc&page=2#{$product->id}");
    }

    public function test_update_ignores_return_route_and_returns_to_index_work_anchor(): void
    {
        $product = Product::factory()->create([
            'progress' => 'Listening',
            'work_name' => 'REDIRECT_TAGS_TOKEN',
        ]);

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => 'Completed',
            'return_route' => 'tags.index',
            'return_query' => [
                'search' => 'rain',
                'page' => '4',
            ],
            'return_fragment' => $product->id,
        ])->assertRedirect("/?progress=Completed#{$product->id}");
    }

    public function test_update_normalizes_empty_listening_fields_to_null(): void
    {
        $product = Product::factory()->create([
            'num_re_listen_times' => 5,
            're_listen_value' => 4,
            'priority' => 2,
            'start_date' => ['month' => '01', 'day' => '01', 'year' => '2025'],
            'end_date' => ['month' => '01', 'day' => '02', 'year' => '2025'],
        ]);

        $response = $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => $product->progress,
            'add' => [
                'start_date' => [
                    'month' => '',
                    'day' => '',
                    'year' => '',
                ],
                'finish_date' => [
                    'month' => '',
                    'day' => '',
                    'year' => '',
                ],
                'num_re_listen_times' => '',
                're_listen_value' => '',
                'priority' => '',
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertNull($product->start_date);
        $this->assertNull($product->end_date);
        $this->assertNull($product->num_re_listen_times);
        $this->assertNull($product->re_listen_value);
        $this->assertNull($product->priority);
    }

    public function test_update_rejects_invalid_date_parts(): void
    {
        $product = Product::factory()->create();

        $response = $this->from("/edit/{$product->id}")
            ->post("/update/{$product->id}", [
                'work_name' => $product->work_name,
                'add' => [
                    'start_date' => [
                        'month' => '13',
                        'day' => '01',
                        'year' => '2025',
                    ],
                ],
            ]);

        $response->assertRedirect("/edit/{$product->id}");
        $response->assertSessionHasErrors(['add.start_date']);
    }

    public function test_update_rejects_invalid_date_order(): void
    {
        $product = Product::factory()->create();

        $response = $this->from("/edit/{$product->id}")
            ->post("/update/{$product->id}", [
                'work_name' => $product->work_name,
                'add' => [
                    'start_date' => [
                        'month' => '05',
                        'day' => '12',
                        'year' => '2025',
                    ],
                    'finish_date' => [
                        'month' => '05',
                        'day' => '01',
                        'year' => '2025',
                    ],
                ],
            ]);

        $response->assertRedirect("/edit/{$product->id}");
        $response->assertSessionHasErrors(['add.finish_date']);
    }

    public function test_destroy_deletes_product_and_redirects(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'DESTROY_TARGET_TOKEN',
        ]);

        $this->post("/destroy/{$product->id}", [
            'return_query' => [
                'progress' => 'Completed',
            ],
        ])->assertRedirect('/?progress=Completed');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_destroy_missing_product_is_safe_no_op_and_redirects(): void
    {
        $missingId = Product::factory()->make()->id;

        $this->post("/destroy/{$missingId}", [
            'return_query' => [
                'progress' => 'Plan to Listen',
            ],
        ])->assertRedirect('/?progress=Plan%20to%20Listen');
    }

    public function test_destroy_clamps_saved_page_to_last_valid_index_page(): void
    {
        Option::setIndexPerPage(2);

        Product::factory()->create(['id' => 'RJ000000003']);
        Product::factory()->create(['id' => 'RJ000000002']);
        $product = Product::factory()->create(['id' => 'RJ000000001']);

        $this->post("/destroy/{$product->id}", [
            'return_query' => [
                'page' => '2',
            ],
        ])->assertRedirect('/');
    }

    public function test_destroy_clamps_saved_page_using_the_filtered_index_result_set(): void
    {
        Option::setIndexPerPage(2);

        Product::factory()->create(['id' => 'RJ000000003', 'progress' => 'Listening']);
        Product::factory()->create(['id' => 'RJ000000002', 'progress' => 'Listening']);
        $product = Product::factory()->create(['id' => 'RJ000000001', 'progress' => 'Listening']);
        Product::factory()->create(['id' => 'RJ000000999', 'progress' => 'Completed']);

        $this->post("/destroy/{$product->id}", [
            'return_query' => [
                'progress' => 'Listening',
                'page' => '2',
            ],
        ])->assertRedirect('/?progress=Listening');
    }

    public function test_destroy_ignores_return_route_and_keeps_index_query(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'DESTROY_TAGS_TOKEN',
        ]);

        $this->post("/destroy/{$product->id}", [
            'return_route' => 'tags.index',
            'return_query' => [
                'search' => 'rain',
            ],
        ])->assertRedirect('/?search=rain');
    }

    public function test_destroy_logs_storage_cleanup_failure_and_deletes_product(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'DESTROY_STORAGE_FAILURE_TOKEN',
        ]);
        $localDisk = Mockery::mock(Filesystem::class);
        $publicDisk = Mockery::mock(Filesystem::class);

        $localDisk
            ->shouldReceive('delete')
            ->once()
            ->with("Works/{$product->id}.json")
            ->andReturn(false);

        $publicDisk
            ->shouldReceive('deleteDirectory')
            ->once()
            ->with("Works/{$product->id}")
            ->andReturn(true);

        Storage::shouldReceive('disk')
            ->once()
            ->with('local')
            ->andReturn($localDisk);

        Storage::shouldReceive('disk')
            ->once()
            ->with('public')
            ->andReturn($publicDisk);

        Log::shouldReceive('warning')
            ->once()
            ->with('Unable to delete product scraper JSON.', [
                'product_id' => $product->id,
                'path' => "Works/{$product->id}.json",
            ]);

        $this->post("/destroy/{$product->id}")
            ->assertRedirect('/');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_destroy_logs_public_image_cleanup_failure_and_deletes_product(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'DESTROY_IMAGE_FAILURE_TOKEN',
        ]);
        $localDisk = Mockery::mock(Filesystem::class);
        $publicDisk = Mockery::mock(Filesystem::class);

        $localDisk
            ->shouldReceive('delete')
            ->once()
            ->with("Works/{$product->id}.json")
            ->andReturn(true);

        $publicDisk
            ->shouldReceive('deleteDirectory')
            ->once()
            ->with("Works/{$product->id}")
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->once()
            ->with('local')
            ->andReturn($localDisk);

        Storage::shouldReceive('disk')
            ->once()
            ->with('public')
            ->andReturn($publicDisk);

        Log::shouldReceive('warning')
            ->once()
            ->with('Unable to delete product image directory.', [
                'product_id' => $product->id,
                'path' => "Works/{$product->id}",
            ]);

        $this->post("/destroy/{$product->id}")
            ->assertRedirect('/');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_edit_returns_404_for_missing_product(): void
    {
        $this->get('/edit/RJ999999999')->assertNotFound();
    }

    private function uniqueToken(string $prefix): string
    {
        return $prefix . '_' . random_int(100000, 999999);
    }

    private function expectedPythonExecutable(): string
    {
        return base_path(
            PHP_OS_FAMILY === 'Windows'
                ? 'python/venv/Scripts/python.exe'
                : 'python/venv/bin/python'
        );
    }

    private function createGenre(string $title, string $type): Genre
    {
        $genre = Genre::query()->create([
            'group_id' => null,
            'title' => $title,
            'description' => null,
            'order' => null,
        ]);

        $genre->setAttribute('type', $type);

        return $genre;
    }

    private function attachGenres(Product $product, array $genres): void
    {
        $fetchedByLanguage = [
            Genre::LANGUAGE_JAPANESE => [],
            Genre::LANGUAGE_ENGLISH => [],
        ];
        $customGenreIds = [];

        foreach ($genres as $genre) {
            match ($genre->getAttribute('type')) {
                Genre::TYPE_AUTO_GENERATED_JAPANESE => $fetchedByLanguage[Genre::LANGUAGE_JAPANESE][] = $genre->getKey(),
                Genre::TYPE_AUTO_GENERATED_ENGLISH => $fetchedByLanguage[Genre::LANGUAGE_ENGLISH][] = $genre->getKey(),
                default => $customGenreIds[] = $genre->getKey(),
            };
        }

        app(ProductGenreSync::class)->sync($product, $fetchedByLanguage, $customGenreIds);
    }

    private function customStorePayload(string $workId, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => strtolower($workId),
            'work_name' => 'CUSTOM_RETURN_TARGET_TOKEN',
            'age_category' => 'ALL_AGES',
            'work_image' => UploadedFile::fake()->image('cover.png')->size(1024),
        ], $overrides);
    }
}
