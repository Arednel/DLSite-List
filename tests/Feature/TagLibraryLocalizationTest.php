<?php

namespace Tests\Feature;

use App\Enums\UiLanguage;
use App\Livewire\TagLibraryManager;
use App\Models\Genre;
use App\Models\GenreGroup;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TagLibraryLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_japanese_tag_library_shell_and_manager_localize_copy_while_values_stay_stable(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);

        $this->get(route('tags.index'))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('<title>タグライブラリ</title>', false)
            ->assertSee('<span class="progress-heading">タグライブラリ</span>', false)
            ->assertSee('<h1 id="tag-library-heading" class="tag-library-section-title">タグ</h1>', false)
            ->assertSee('placeholder="タグを検索…"', false)
            ->assertSee('<h2 class="tag-library-section-title">すべてのタグ</h2>', false)
            ->assertSee('<option value="hidden_group">グループで非表示</option>', false)
            ->assertSee('data-autocomplete-source="tags"', false);
    }

    public function test_japanese_dynamic_labels_keep_user_tag_and_group_titles_unchanged(): void
    {
        App::setLocale(UiLanguage::Japanese->value);
        $group = GenreGroup::query()->create([
            'title' => 'RAW_USER_GROUP_TITLE',
            'description' => null,
            'order' => 1,
        ]);
        $tag = Genre::resolveByTitle('RAW_USER_TAG_TITLE');
        $tag->forceFill(['hidden_on_index' => true])->save();
        $this->attachTagToGroup($group, $tag);

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->set('tagEditMode', true)
            ->assertSee('RAW_USER_TAG_TITLE')
            ->assertSee('RAW_USER_GROUP_TITLE')
            ->assertSee('aria-label="タグ「RAW_USER_TAG_TITLE」の設定を編集"', false)
            ->assertSee('aria-label="グループ「RAW_USER_GROUP_TITLE」をドラッグ"', false)
            ->assertSee('value="all"', false)
            ->call('openTagSettings', $tag->getKey())
            ->assertSee('タグ設定を編集')
            ->assertSee('aria-label="「RAW_USER_GROUP_TITLE」への割り当てを解除"', false);

        $this->assertSame('RAW_USER_TAG_TITLE', $tag->refresh()->title);
        $this->assertSame('RAW_USER_GROUP_TITLE', $group->refresh()->title);
        $this->assertTrue((bool) $tag->hidden_on_index);
    }

    public function test_empty_state_uses_the_current_fetched_language_code_and_japanese_messages_are_localized(): void
    {
        App::setLocale(UiLanguage::English->value);
        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->assertSee('No EN, custom, or empty tags found.');

        App::setLocale(UiLanguage::Japanese->value);
        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->assertSee('JPタグ、カスタムタグ、未使用タグはありません。');

        Livewire::test(TagLibraryManager::class)
            ->set('newTagTitle', '   ')
            ->call('createTag')
            ->assertHasErrors(['newTagTitle' => 'タグ名を入力してください。']);

        Livewire::test(TagLibraryManager::class)
            ->set('newTagTitle', 'RAW_CREATED_TAG_TITLE')
            ->call('createTag')
            ->assertSee('タグを作成しました。');

        $this->assertDatabaseHas('genres', ['title' => 'RAW_CREATED_TAG_TITLE']);
    }

    private function attachTagToGroup(GenreGroup $group, Genre $tag): void
    {
        DB::table('genre_group_genre')->insert([
            'genre_group_id' => $group->getKey(),
            'genre_id' => $tag->getKey(),
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
