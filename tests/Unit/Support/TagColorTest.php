<?php

namespace Tests\Unit\Support;

use App\Models\Genre;
use App\Models\GenreGroup;
use App\Support\TagColor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_any_configured_colors_returns_false_when_no_tag_or_group_colors_exist(): void
    {
        Genre::resolveByTitle('Uncolored Tag');
        GenreGroup::query()->create([
            'title' => 'Uncolored Group',
            'description' => null,
            'order' => 1,
        ]);

        $this->assertFalse(TagColor::hasAnyConfiguredColors());
    }

    public function test_has_any_configured_colors_detects_tag_background_colors(): void
    {
        Genre::resolveByTitle('Colored Tag')
            ->forceFill(['color' => '#123456'])
            ->save();

        $this->assertTrue(TagColor::hasAnyConfiguredColors());
    }

    public function test_has_any_configured_colors_detects_tag_font_colors(): void
    {
        Genre::resolveByTitle('Font Colored Tag')
            ->forceFill(['text_color' => '#654321'])
            ->save();

        $this->assertTrue(TagColor::hasAnyConfiguredColors());
    }

    public function test_has_any_configured_colors_detects_group_background_colors(): void
    {
        GenreGroup::query()->create([
            'title' => 'Colored Group',
            'description' => null,
            'order' => 1,
            'color' => '#abcdef',
        ]);

        $this->assertTrue(TagColor::hasAnyConfiguredColors());
    }

    public function test_has_any_configured_colors_detects_group_font_colors(): void
    {
        GenreGroup::query()->create([
            'title' => 'Font Colored Group',
            'description' => null,
            'order' => 1,
            'text_color' => '#fedcba',
        ]);

        $this->assertTrue(TagColor::hasAnyConfiguredColors());
    }

    public function test_view_data_centralizes_tag_color_decoration_fields(): void
    {
        $this->assertSame([
            'color' => '#aa3366',
            'text_color' => '#111111',
            'color_style' => '--tag-color: #aa3366; --tag-text-color: #111111;',
            'has_background_color' => true,
            'has_font_color' => true,
        ], TagColor::viewData('#AA3366', '#111111'));
    }

    public function test_view_data_normalizes_empty_or_invalid_values_to_default_decoration(): void
    {
        $this->assertSame([
            'color' => null,
            'text_color' => null,
            'color_style' => '',
            'has_background_color' => false,
            'has_font_color' => false,
        ], TagColor::viewData('', 'not-a-color'));
    }

    public function test_effective_color_pairs_resolve_group_background_and_font_colors_with_one_group_query(): void
    {
        $genre = Genre::resolveByTitle('Batched Color Pair Tag');
        $genre->forceFill([
            'color' => '#111111',
            'text_color' => '#222222',
        ])->save();
        $fontGroup = GenreGroup::query()->create([
            'title' => 'Batched Font Group',
            'description' => null,
            'order' => 1,
            'text_color' => '#abcdef',
        ]);
        $backgroundGroup = GenreGroup::query()->create([
            'title' => 'Batched Background Group',
            'description' => null,
            'order' => 2,
            'color' => '#fedcba',
        ]);

        $this->attachTagToGroup($fontGroup, $genre, 1);
        $this->attachTagToGroup($backgroundGroup, $genre, 1);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $colors = TagColor::effectiveColorPairsForGenreIds([$genre->getKey()]);

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame('#fedcba', $colors->get($genre->getKey())['color']);
        $this->assertSame('#abcdef', $colors->get($genre->getKey())['text_color']);
        $this->assertCount(1, array_filter(
            $queryLog,
            fn(array $query): bool => str_contains($query['query'], 'genre_group_genre')
                && str_contains($query['query'], 'genre_groups')
        ));
    }

    private function attachTagToGroup(GenreGroup $group, Genre $genre, int $order): void
    {
        DB::table('genre_group_genre')->insert([
            'genre_group_id' => $group->getKey(),
            'genre_id' => $genre->getKey(),
            'order' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
