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

    public function test_resolve_by_title_creates_missing_genre(): void
    {
        $resolved = Genre::resolveByTitle('New Genre');

        $this->assertSame('New Genre', $resolved->title);
        $this->assertDatabaseHas('genres', [
            'title' => 'New Genre',
            'group_id' => null,
            'description' => null,
            'order' => null,
        ]);
    }
}
