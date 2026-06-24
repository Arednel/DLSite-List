<?php

namespace Tests\Unit\Models;

use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_by_title_reuses_existing_genre(): void
    {
        $existing = Genre::query()->create(['title' => 'Existing']);

        $resolved = Genre::resolveByTitle('Existing');

        $this->assertTrue($existing->is($resolved));
        $this->assertSame(1, Genre::query()->where('title', 'Existing')->count());
    }

    public function test_resolve_by_title_reuses_case_only_matches_without_renaming(): void
    {
        $existing = Genre::query()->create(['title' => 'ASMR']);

        $resolved = Genre::resolveByTitle('asmr');

        $this->assertTrue($existing->is($resolved));
        $this->assertSame('ASMR', $resolved->title);
        $this->assertSame(Genre::titleKey('ASMR'), $resolved->title_key);
        $this->assertSame(1, Genre::query()->where('title_key', Genre::titleKey('asmr'))->count());
    }

    public function test_resolve_by_title_keeps_hiragana_and_katakana_variants_distinct(): void
    {
        $hiragana = Genre::resolveByTitle('かなタグ');
        $katakana = Genre::resolveByTitle('カナタグ');

        $this->assertFalse($hiragana->is($katakana));
        $this->assertSame('かなタグ', $hiragana->title);
        $this->assertSame('カナタグ', $katakana->title);
        $this->assertSame(2, Genre::query()->whereIn('title', ['かなタグ', 'カナタグ'])->count());
    }

    public function test_resolve_ids_from_titles_deduplicates_case_only_variants_but_keeps_kana_variants(): void
    {
        $ids = Genre::resolveIdsFromTitles(['ASMR', 'asmr', 'かなタグ', 'カナタグ']);
        $titlesById = Genre::query()
            ->whereIn('id', $ids)
            ->pluck('title', 'id');

        $this->assertCount(3, $ids);
        $this->assertSame(['ASMR', 'かなタグ', 'カナタグ'], collect($ids)
            ->map(fn(int|string $id): string => $titlesById[$id])
            ->all());
    }

    public function test_resolve_by_title_creates_missing_genre(): void
    {
        $resolved = Genre::resolveByTitle('New Genre');

        $this->assertSame('New Genre', $resolved->title);
        $this->assertSame('new genre', $resolved->title_key);
        $this->assertDatabaseHas('genres', [
            'title' => 'New Genre',
            'title_key' => 'new genre',
            'description' => null,
            'order' => 1,
        ]);
    }
}
