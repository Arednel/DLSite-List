<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $noiseGenre = $this->createGenre("SEARCH_NOISE_GENRE_{$token}", Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $noiseCustomGenre = $this->createGenre("SEARCH_NOISE_CUSTOM_{$token}", Genre::TYPE_CUSTOM);

        $target = Product::factory()->create([
            'work_name' => $jpToken,
            'work_name_english' => $enToken,
            'series' => $seriesToken,
        ]);
        $this->attachGenres($target, [$genre, $customGenre]);

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
            ->assertDontSee('Resolved Japanese Tag');
    }

    public function test_index_filters_by_genre_id_from_view_links(): void
    {
        $sharedGenre = $this->createGenre('Linked Tag', Genre::TYPE_AUTO_GENERATED_ENGLISH);

        $matching = Product::factory()->create([
            'work_name' => 'GENRE_ID_MATCH_TOKEN',
        ]);
        $this->attachGenres($matching, [$sharedGenre]);

        $noise = Product::factory()->create([
            'work_name' => 'GENRE_ID_NOISE_TOKEN',
        ]);

        $this->get('/?genre=' . $sharedGenre->getKey())
            ->assertOk()
            ->assertSee($matching->work_name)
            ->assertDontSee($noise->work_name);
    }

    public function test_tag_library_lists_clickable_english_and_custom_genres(): void
    {
        $englishGenre = $this->createGenre('Library English Tag', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $customGenre = $this->createGenre('Library Custom Tag', Genre::TYPE_CUSTOM);
        $this->createGenre('Library Japanese Tag', Genre::TYPE_AUTO_GENERATED_JAPANESE);

        $response = $this->from('/?progress=Listening')->get('/tags');

        $response->assertOk()
            ->assertSee('Tag Library')
            ->assertSee('Quick Add')
            ->assertSee('Library English Tag')
            ->assertSee('Library Custom Tag')
            ->assertDontSee('Library Japanese Tag')
            ->assertSee('genre=' . $englishGenre->getKey(), false)
            ->assertSee('genre=' . $customGenre->getKey(), false)
            ->assertSee('href="/create?redirect=', false)
            ->assertDontSee('hero__back', false);
    }

    public function test_create_renders_form_page(): void
    {
        $this->get('/create')
            ->assertOk()
            ->assertSee('Add Work')
            ->assertSee('Custom Tags')
            ->assertSee('name="id"', false)
            ->assertSee('id="add_start_date_month"', false)
            ->assertSee('id="add_finish_date_month"', false);
    }

    public function test_edit_renders_form_page_for_existing_product(): void
    {
        $japaneseGenre = $this->createGenre('Sleep Guidance', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $englishGenre = $this->createGenre('Sleep Guidance EN', Genre::TYPE_AUTO_GENERATED_ENGLISH);

        $product = Product::factory()->create([
            'work_name' => 'EDIT_VIEW_NAME_TOKEN',
            'work_name_english' => 'EDIT_VIEW_EN_TOKEN',
            'notes' => 'EDIT_VIEW_NOTES_TOKEN',
        ]);
        $this->attachGenres($product, [$japaneseGenre, $englishGenre]);

        $this->get("/edit/{$product->id}?redirect=/?progress=Listening")
            ->assertOk()
            ->assertSee('Edit Work')
            ->assertSee($product->id)
            ->assertSee($product->work_name)
            ->assertSee($product->work_name_english)
            ->assertSee('Fetched EN Genres')
            ->assertSee('Sleep Guidance')
            ->assertSee('Sleep Guidance EN')
            ->assertSee($product->notes)
            ->assertSee('name="redirect"', false);
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
        $urlInput = "https://www.dlsite.com/maniax/work/=/product_id/" . strtolower($existing->id) . ".html";

        $response = $this->from('/create')->post('/store', [
            'id' => $urlInput,
        ]);

        $response->assertRedirect('/create');
        $response->assertSessionHasErrors(['id']);

        $this->assertSame('This RJ work is already in the database', session('errors')->first('id'));
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

    public function test_update_requires_work_name_and_saves_listening_fields(): void
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

        $this->from("/edit/{$product->id}")
            ->post("/update/{$product->id}", [
                'progress' => 'Listening',
            ])
            ->assertRedirect("/edit/{$product->id}")
            ->assertSessionHasErrors(['work_name']);

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
            'redirect' => '/?progress=Plan%20to%20Listen',
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

    public function test_update_attaches_existing_auto_generated_genre_when_user_adds_matching_title(): void
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
        $this->assertSame(
            ['Existing Auto Genre'],
            $product->englishGenres->pluck('title')->all()
        );
        $this->assertSame([], $product->customGenres->pluck('title')->all());
        $this->assertSame(1, Genre::query()->where('title', 'Existing Auto Genre')->count());
    }

    public function test_update_redirect_rewrites_progress_when_value_changes(): void
    {
        $product = Product::factory()->create([
            'progress' => 'Listening',
            'work_name' => 'REDIRECT_CHANGED_TOKEN',
        ]);

        $redirect = '/?age_category=ALL_AGES&progress=Listening&search=rain';

        $this->post("/update/{$product->id}", [
            'work_name' => $product->work_name,
            'progress' => 'Completed',
            'redirect' => $redirect,
        ])->assertRedirect("/?age_category=ALL_AGES&search=rain&progress=Completed#{$product->id}");
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
            'redirect' => $redirect,
        ])->assertRedirect("{$redirect}#{$product->id}");
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
            'redirect' => '/?progress=Completed',
        ])->assertRedirect('/?progress=Completed');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_destroy_missing_product_is_safe_no_op_and_redirects(): void
    {
        $missingId = Product::factory()->make()->id;

        $this->post("/destroy/{$missingId}", [
            'redirect' => '/?progress=Plan%20to%20Listen',
        ])->assertRedirect('/?progress=Plan%20to%20Listen');
    }

    public function test_edit_returns_404_for_missing_product(): void
    {
        $this->get('/edit/RJ999999999')->assertNotFound();
    }

    private function uniqueToken(string $prefix): string
    {
        return $prefix . '_' . random_int(100000, 999999);
    }

    private function createGenre(string $title, string $type): Genre
    {
        return Genre::query()->create([
            'group_id' => null,
            'title' => $title,
            'description' => null,
            'order' => null,
            'type' => $type,
            'language' => $type === Genre::TYPE_AUTO_GENERATED_JAPANESE
                ? Genre::LANGUAGE_JAPANESE
                : Genre::LANGUAGE_ENGLISH,
        ]);
    }

    private function attachGenres(Product $product, array $genres): void
    {
        $product->genres()->sync(
            collect($genres)
                ->map(fn(Genre $genre) => $genre->getKey())
                ->all()
        );
    }
}
