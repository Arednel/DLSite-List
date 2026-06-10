<?php

namespace Tests\Feature;

use App\Livewire\TagLibraryManager;
use App\Models\Genre;
use App\Models\Option;
use App\Models\Product;
use App\Support\ProductGenreSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TagLibraryManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_visible_and_empty_tags_but_excludes_attached_japanese_only_tags(): void
    {
        $englishGenre = $this->createGenre('Library English Tag', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $customGenre = $this->createGenre('Library Custom Tag', Genre::TYPE_CUSTOM);
        $emptyGenre = Genre::resolveByTitle('Library Empty Tag');
        $japaneseGenre = $this->createGenre('Library Japanese Tag', Genre::TYPE_AUTO_GENERATED_JAPANESE);

        $firstProduct = Product::factory()->create();
        $secondProduct = Product::factory()->create();
        $japaneseProduct = Product::factory()->create();

        $this->attachGenres($firstProduct, [$englishGenre, $customGenre]);
        $this->attachGenres($secondProduct, [$englishGenre]);
        $this->attachGenres($japaneseProduct, [$japaneseGenre]);

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->assertSee('Library Custom Tag')
            ->assertSee('Library English Tag')
            ->assertSee('Library Empty Tag')
            ->assertSee('tag-library-tag-count">2</span>', false)
            ->assertSee('tag-library-tag-count tag-library-tag-count--empty">0</span>', false)
            ->assertDontSee('Library Japanese Tag')
            ->assertSee('genre=' . $englishGenre->getKey(), false)
            ->assertSee('genre=' . $customGenre->getKey(), false)
            ->assertSee('genre=' . $emptyGenre->getKey(), false);
    }

    public function test_all_tags_starts_collapsed_and_search_opens_matching_results(): void
    {
        Genre::resolveByTitle('Collapsed Search Match');
        Genre::resolveByTitle('Collapsed Hidden Noise');

        Livewire::test(TagLibraryManager::class)
            ->assertSet('showAllTags', false)
            ->assertDontSee('Collapsed Search Match')
            ->set('search', 'Match')
            ->assertSet('showAllTags', true)
            ->assertSee('Collapsed Search Match')
            ->assertDontSee('Collapsed Hidden Noise');
    }

    public function test_all_tags_can_start_expanded_from_option(): void
    {
        Option::setTagLibraryTagsExpandedByDefault(true);
        Genre::resolveByTitle('Default Expanded Tag');

        Livewire::test(TagLibraryManager::class)
            ->assertSet('showAllTags', true)
            ->assertSee('Default Expanded Tag');
    }

    public function test_creates_new_empty_tag_with_normalized_title_key(): void
    {
        Livewire::test(TagLibraryManager::class)
            ->set('newTagTitle', '  New Empty Stage Tag  ')
            ->call('createTag')
            ->assertSet('newTagTitle', '')
            ->assertSet('search', 'New Empty Stage Tag')
            ->assertSet('showAllTags', true)
            ->assertSee('New Empty Stage Tag')
            ->assertSee('Tag created.');

        $this->assertDatabaseHas('genres', [
            'title' => 'New Empty Stage Tag',
            'title_key' => Genre::titleKey('New Empty Stage Tag'),
        ]);

        $this->assertSame(0, DB::table('genre_product')
            ->where('genre_id', Genre::query()->where('title', 'New Empty Stage Tag')->value('id'))
            ->count());
    }

    public function test_duplicate_title_does_not_create_another_tag(): void
    {
        $existing = Genre::resolveByTitle('Existing Duplicate Tag');

        Livewire::test(TagLibraryManager::class)
            ->set('newTagTitle', ' existing duplicate tag ')
            ->call('createTag')
            ->assertSet('newTagTitle', '')
            ->assertSet('search', 'Existing Duplicate Tag')
            ->assertSet('showAllTags', true)
            ->assertSee('Tag already exists.');

        $this->assertSame(1, Genre::query()
            ->where('title_key', Genre::titleKey('Existing Duplicate Tag'))
            ->count());

        $this->assertDatabaseHas('genres', [
            'id' => $existing->getKey(),
            'title' => 'Existing Duplicate Tag',
        ]);
    }

    public function test_empty_tag_delete_confirmation_can_be_opened_and_cancelled(): void
    {
        $emptyGenre = Genre::resolveByTitle('Delete Cancel Empty Tag');

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->call('askDeleteTag', $emptyGenre->getKey())
            ->assertSet('confirmingDeleteTagId', $emptyGenre->getKey())
            ->assertSee('Delete empty tag "Delete Cancel Empty Tag"?', false)
            ->call('cancelDeleteTag')
            ->assertSet('confirmingDeleteTagId', null)
            ->assertSee('Delete Cancel Empty Tag');

        $this->assertDatabaseHas('genres', ['id' => $emptyGenre->getKey()]);
    }

    public function test_deletes_empty_tag_after_confirmation(): void
    {
        $emptyGenre = Genre::resolveByTitle('Delete Confirm Empty Tag');

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->call('askDeleteTag', $emptyGenre->getKey())
            ->call('deleteTag')
            ->assertSet('confirmingDeleteTagId', null)
            ->assertSee('Empty tag deleted.')
            ->assertDontSee('Delete Confirm Empty Tag');

        $this->assertDatabaseMissing('genres', ['id' => $emptyGenre->getKey()]);
    }

    public function test_delete_refuses_tag_that_gained_a_pivot_before_confirmation(): void
    {
        $genre = Genre::resolveByTitle('Delete Race Tag');
        $product = Product::factory()->create();

        $component = Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->call('askDeleteTag', $genre->getKey());

        app(ProductGenreSync::class)->syncCustom($product, [$genre->getKey()]);

        $component
            ->call('deleteTag')
            ->assertSet('confirmingDeleteTagId', null)
            ->assertSee('Only empty tags can be deleted.')
            ->assertSee('Delete Race Tag');

        $this->assertDatabaseHas('genres', ['id' => $genre->getKey()]);
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
}
