<?php

namespace Tests\Feature;

use App\Livewire\TagLibraryManager;
use App\Models\Genre;
use App\Models\GenreGroup;
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
            ->assertSet('search', '')
            ->assertSet('showAllTags', true)
            ->assertSee('New Empty Stage Tag')
            ->assertSee('Tag created.');

        $this->assertDatabaseHas('genres', [
            'title' => 'New Empty Stage Tag',
            'title_key' => Genre::titleKey('New Empty Stage Tag'),
        ]);
        $this->assertNotNull(Genre::query()->where('title', 'New Empty Stage Tag')->value('order'));

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

    public function test_create_tag_preserves_validation_messages(): void
    {
        Livewire::test(TagLibraryManager::class)
            ->set('newTagTitle', '   ')
            ->call('createTag')
            ->assertHasErrors(['newTagTitle' => 'Enter a tag title.']);

        Livewire::test(TagLibraryManager::class)
            ->set('newTagTitle', str_repeat('A', 256))
            ->call('createTag')
            ->assertHasErrors([
                'newTagTitle' => 'Tag titles may not be greater than 255 characters.',
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
        $secondEmptyGenre = Genre::resolveByTitle('Delete Confirm Second Empty Tag');

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->call('askDeleteTag', $emptyGenre->getKey())
            ->call('deleteTag')
            ->assertSet('confirmingDeleteTagId', null)
            ->assertSet('noticeId', 1)
            ->assertSee('Empty tag deleted.')
            ->assertDontSee('Delete Confirm Empty Tag')
            ->call('askDeleteTag', $secondEmptyGenre->getKey())
            ->call('deleteTag')
            ->assertSet('confirmingDeleteTagId', null)
            ->assertSet('noticeId', 2)
            ->assertSee('Empty tag deleted.')
            ->assertDontSee('Delete Confirm Second Empty Tag');

        $this->assertDatabaseMissing('genres', ['id' => $emptyGenre->getKey()]);
        $this->assertDatabaseMissing('genres', ['id' => $secondEmptyGenre->getKey()]);
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

    public function test_creates_renames_and_rejects_duplicate_group_titles(): void
    {
        $existing = GenreGroup::query()->create([
            'title' => 'Existing Group',
            'description' => null,
            'order' => 1,
        ]);

        Livewire::test(TagLibraryManager::class)
            ->set('newGroupTitle', '  New Group  ')
            ->call('createGroup')
            ->assertSet('newGroupTitle', '')
            ->assertSee('New Group')
            ->assertSee('Tag group created.');

        $group = GenreGroup::query()->where('title', 'New Group')->firstOrFail();

        Livewire::test(TagLibraryManager::class)
            ->set("groupTitles.{$group->getKey()}", ' Renamed Group ')
            ->call('renameGroup', $group->getKey())
            ->assertSee('Renamed Group')
            ->assertSee('Tag group renamed.')
            ->set("groupTitles.{$group->getKey()}", 'Existing Group')
            ->call('renameGroup', $group->getKey())
            ->assertSee('Tag group title already exists.');

        $this->assertDatabaseHas('genre_groups', [
            'id' => $group->getKey(),
            'title' => 'Renamed Group',
        ]);
        $this->assertNotNull(GenreGroup::query()->findOrFail($group->getKey())->order);
        $this->assertDatabaseHas('genre_groups', ['id' => $existing->getKey()]);
    }

    public function test_add_group_form_renders_inside_tag_groups_section(): void
    {
        $html = Livewire::test(TagLibraryManager::class)->html();

        $tagGroupsHeadingPosition = strpos($html, 'id="tag-library-groups-heading"');
        $addGroupFormPosition = strpos($html, 'wire:submit.prevent="createGroup"');
        $addGroupInputPosition = strpos($html, 'id="new-group-title"');

        $this->assertIsInt($tagGroupsHeadingPosition);
        $this->assertIsInt($addGroupFormPosition);
        $this->assertIsInt($addGroupInputPosition);
        $this->assertGreaterThan($tagGroupsHeadingPosition, $addGroupFormPosition);
        $this->assertGreaterThan($tagGroupsHeadingPosition, $addGroupInputPosition);
    }

    public function test_index_group_ordering_toggle_renders_in_tag_groups_and_persists_immediately(): void
    {
        Option::setTagLibraryIndexGroupOrderingEnabled(true);

        Livewire::test(TagLibraryManager::class)
            ->assertSet('indexGroupOrderingEnabled', true)
            ->assertSee('Enable group ordering on Index')
            ->assertSee('wire:model.live="indexGroupOrderingEnabled"', false)
            ->set('indexGroupOrderingEnabled', false)
            ->assertSet('indexGroupOrderingEnabled', false)
            ->assertSee('Index group ordering disabled.');

        $this->assertFalse(Option::tagLibraryIndexGroupOrderingEnabled());
    }

    public function test_model_creation_normalizes_null_group_and_tag_orders(): void
    {
        $group = GenreGroup::query()->create([
            'title' => 'Null Order Group',
            'description' => null,
            'order' => null,
        ]);
        $groupedTag = Genre::query()->create([
            'title' => 'Null Order Grouped Tag',
            'description' => null,
            'order' => null,
        ]);
        $ungroupedTag = Genre::query()->create([
            'title' => 'Null Order Ungrouped Tag',
            'description' => null,
            'order' => null,
        ]);

        $this->assertSame(1, $group->refresh()->order);
        $this->assertSame(1, $groupedTag->refresh()->order);
        $this->assertSame(2, $ungroupedTag->refresh()->order);
    }

    public function test_deleting_group_removes_only_that_groups_memberships_without_deleting_tags(): void
    {
        $group = GenreGroup::query()->create([
            'title' => 'Delete Group',
            'description' => null,
            'order' => 1,
            'hidden_on_index' => true,
        ]);
        $otherGroup = GenreGroup::query()->create([
            'title' => 'Other Group',
            'description' => null,
            'order' => 2,
        ]);
        $hiddenTag = Genre::resolveByTitle('Hidden Member');
        $visibleTag = Genre::resolveByTitle('Visible Member');
        $hiddenTag->forceFill(['hidden_on_index' => true])->save();
        $this->attachTagToGroup($group, $hiddenTag, 1);
        $this->attachTagToGroup($otherGroup, $hiddenTag, 1);
        $this->attachTagToGroup($group, $visibleTag, 2);

        $component = Livewire::test(TagLibraryManager::class)
            ->set("groupColors.{$group->getKey()}", '#112233')
            ->set("groupTextColors.{$group->getKey()}", '#445566')
            ->call('askDeleteGroup', $group->getKey())
            ->assertSet('confirmingDeleteGroupId', $group->getKey())
            ->assertSee('Delete group "Delete Group"?', false)
            ->call('deleteGroup')
            ->assertSee('Tag group deleted.');

        $this->assertArrayNotHasKey($group->getKey(), $component->get('groupColors'));
        $this->assertArrayNotHasKey($group->getKey(), $component->get('groupTextColors'));
        $this->assertDatabaseMissing('genre_groups', ['id' => $group->getKey()]);
        $this->assertDatabaseHas('genres', [
            'id' => $hiddenTag->getKey(),
            'hidden_on_index' => true,
        ]);
        $this->assertDatabaseHas('genres', [
            'id' => $visibleTag->getKey(),
            'hidden_on_index' => false,
        ]);
        $this->assertDatabaseMissing('genre_group_genre', ['genre_group_id' => $group->getKey()]);
        $this->assertDatabaseHas('genre_group_genre', [
            'genre_group_id' => $otherGroup->getKey(),
            'genre_id' => $hiddenTag->getKey(),
        ]);
    }

    public function test_adds_existing_and_new_tags_to_groups_and_removes_them(): void
    {
        $firstGroup = GenreGroup::query()->create([
            'title' => 'First Attach Group',
            'description' => null,
            'order' => 1,
        ]);
        $secondGroup = GenreGroup::query()->create([
            'title' => 'Second Attach Group',
            'description' => null,
            'order' => 2,
        ]);
        $existing = Genre::resolveByTitle('Existing Group Tag');
        $this->attachTagToGroup($firstGroup, $existing, 1);

        Livewire::test(TagLibraryManager::class)
            ->set("groupTagInputs.{$secondGroup->getKey()}", ' existing group tag ')
            ->call('addTagToGroup', $secondGroup->getKey())
            ->assertSee('Tag added to group.')
            ->set("groupTagInputs.{$secondGroup->getKey()}", ' existing group tag ')
            ->call('addTagToGroup', $secondGroup->getKey())
            ->assertSee('Tag is already in this group.')
            ->set("groupTagInputs.{$secondGroup->getKey()}", ' New Group Tag ')
            ->call('addTagToGroup', $secondGroup->getKey())
            ->assertSee('New Group Tag')
            ->assertSee('Tag added to group.')
            ->call('removeTagFromGroup', $secondGroup->getKey(), $existing->getKey())
            ->assertSee('Tag removed from group.');

        $new = Genre::query()->where('title', 'New Group Tag')->firstOrFail();

        $this->assertDatabaseHas('genre_group_genre', [
            'genre_group_id' => $firstGroup->getKey(),
            'genre_id' => $existing->getKey(),
        ]);
        $this->assertDatabaseMissing('genre_group_genre', [
            'genre_group_id' => $secondGroup->getKey(),
            'genre_id' => $existing->getKey(),
        ]);
        $this->assertDatabaseHas('genre_group_genre', [
            'genre_group_id' => $secondGroup->getKey(),
            'genre_id' => $new->getKey(),
        ]);
    }

    public function test_moves_groups_and_group_tags_with_buttons_and_sort_actions(): void
    {
        $firstGroup = GenreGroup::query()->create(['title' => 'First Group', 'description' => null, 'order' => 1]);
        $secondGroup = GenreGroup::query()->create(['title' => 'Second Group', 'description' => null, 'order' => 2]);
        $firstTag = Genre::resolveByTitle('First Ordered Tag');
        $secondTag = Genre::resolveByTitle('Second Ordered Tag');
        $this->attachTagToGroup($firstGroup, $firstTag, 1);
        $this->attachTagToGroup($firstGroup, $secondTag, 2);

        Livewire::test(TagLibraryManager::class)
            ->call('moveGroup', $secondGroup->getKey(), -1)
            ->call('moveGroupTag', $firstGroup->getKey(), $secondTag->getKey(), -1)
            ->call('reorderGroups', (string) $firstGroup->getKey(), 1)
            ->call('reorderGroupTags', "{$firstGroup->getKey()}|{$firstTag->getKey()}", 1);

        $this->assertSame(1, GenreGroup::query()->findOrFail($secondGroup->getKey())->order);
        $this->assertSame(2, GenreGroup::query()->findOrFail($firstGroup->getKey())->order);
        $this->assertSame(1, $this->groupTagOrder($firstGroup, $secondTag));
        $this->assertSame(2, $this->groupTagOrder($firstGroup, $firstTag));
    }

    public function test_group_and_tag_hidden_settings_are_independent(): void
    {
        $group = GenreGroup::query()->create([
            'title' => 'Hidden Override Group',
            'description' => null,
            'order' => 1,
            'hidden_on_index' => false,
        ]);
        $tag = Genre::resolveByTitle('Independent Hidden Tag');
        $tag->forceFill(['hidden_on_index' => false])->save();
        $this->attachTagToGroup($group, $tag, 1);

        Livewire::test(TagLibraryManager::class)
            ->set("groupHidden.{$group->getKey()}", true)
            ->call('saveGroupHidden', $group->getKey())
            ->set("tagHidden.{$tag->getKey()}", true)
            ->call('saveTagHidden', $tag->getKey())
            ->set("groupHidden.{$group->getKey()}", false)
            ->call('saveGroupHidden', $group->getKey());

        $this->assertDatabaseHas('genre_groups', [
            'id' => $group->getKey(),
            'hidden_on_index' => false,
        ]);
        $this->assertDatabaseHas('genres', [
            'id' => $tag->getKey(),
            'hidden_on_index' => true,
        ]);
    }

    public function test_hide_group_on_index_control_renders_as_switch(): void
    {
        $group = GenreGroup::query()->create([
            'title' => 'Switch Hidden Group Control',
            'description' => null,
            'order' => 1,
        ]);

        $html = Livewire::test(TagLibraryManager::class)->html();

        $this->assertStringContainsString('class="tag-library-check tag-library-switch"', $html);
        $this->assertStringContainsString('wire:model.live="groupHidden.' . $group->getKey() . '"', $html);
        $this->assertStringContainsString(
            'wire:change="saveGroupHidden(' . $group->getKey() . ')" role="switch"',
            $html,
        );
        $this->assertStringContainsString('class="tag-library-switch-track"', $html);
        $this->assertStringContainsString('class="tag-library-switch-thumb"', $html);
        $this->assertStringContainsString('class="tag-library-switch-text">Hide group on Index</span>', $html);
        $this->assertStringNotContainsString('tag-library-setting-toggle', $html);
    }

    public function test_tag_edit_mode_replaces_index_links_with_modal_triggers(): void
    {
        $genre = Genre::resolveByTitle('Editable Tag Link');

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->assertSet('tagEditMode', false)
            ->assertSee('genre=' . $genre->getKey(), false)
            ->assertDontSee('wire:click="openTagSettings(' . $genre->getKey() . ')"', false)
            ->set('tagEditMode', true)
            ->assertSee('wire:click="openTagSettings(' . $genre->getKey() . ')"', false)
            ->assertDontSee('href="' . route('index', ['age_category' => '', 'progress' => '', 'genre' => $genre->getKey()]) . '"', false);
    }

    public function test_tag_edit_mode_toggle_renders_as_accessible_switch(): void
    {
        Livewire::test(TagLibraryManager::class)
            ->assertSet('tagEditMode', false)
            ->assertSee('class="tag-library-switch tag-library-switch--toolbar"', false)
            ->assertSee('class="tag-library-switch-input"', false)
            ->assertSee('type="checkbox" class="tag-library-switch-input" wire:model.live="tagEditMode"', false)
            ->assertSee('class="tag-library-switch-track"', false)
            ->assertSee('class="tag-library-switch-thumb"', false)
            ->assertSee('class="tag-library-switch-text">Edit tags</span>', false)
            ->assertDontSee('tag-library-edit-toggle', false)
            ->set('tagEditMode', true)
            ->assertSet('tagEditMode', true);
    }

    public function test_tag_edit_mode_opens_tag_settings_modal(): void
    {
        $group = GenreGroup::query()->create([
            'title' => 'Modal Existing Group',
            'description' => null,
            'order' => 1,
        ]);
        $genre = Genre::resolveByTitle('Modal Editable Tag');
        $genre->forceFill(['hidden_on_index' => true])->save();
        $this->attachTagToGroup($group, $genre, 1);

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->set('tagEditMode', true)
            ->call('openTagSettings', $genre->getKey())
            ->assertSet('editingTagId', $genre->getKey())
            ->assertSet('editingTagHidden', true)
            ->assertSet('editingTagGroupIds', [$group->getKey()])
            ->assertSee('Edit tag settings')
            ->assertSee('Modal Editable Tag')
            ->assertSee('Modal Existing Group');
    }

    public function test_hide_tag_on_index_controls_render_as_switches(): void
    {
        $group = GenreGroup::query()->create([
            'title' => 'Switch Hidden Group',
            'description' => null,
            'order' => 1,
        ]);
        $genre = Genre::resolveByTitle('Switch Hidden Tag');
        $this->attachTagToGroup($group, $genre, 1);

        $html = Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->html();

        $this->assertStringContainsString('class="tag-library-check tag-library-switch"', $html);
        $this->assertStringContainsString('wire:sort:ignore', $html);
        $this->assertStringContainsString(
            'class="tag-library-switch-input"',
            $html,
        );
        $this->assertStringContainsString('wire:model.live="tagHidden.' . $genre->getKey() . '"', $html);
        $this->assertStringContainsString(
            'wire:change="saveTagHidden(' . $genre->getKey() . ')" role="switch"',
            $html,
        );
        $this->assertStringContainsString(
            '<label class="tag-library-check tag-library-switch">',
            $html,
        );
        $this->assertStringContainsString('wire:model="editingTagHidden"', $html);
        $this->assertStringContainsString('role="switch"', $html);
        $this->assertStringContainsString('class="tag-library-switch-track"', $html);
        $this->assertStringContainsString('class="tag-library-switch-thumb"', $html);
        $this->assertSame(2, substr_count($html, 'class="tag-library-switch-text">Hide tag on Index</span>'));
        $this->assertStringNotContainsString('tag-library-setting-toggle', $html);
    }

    public function test_tag_settings_group_search_adds_and_removes_group_plaques(): void
    {
        $selectedGroup = GenreGroup::query()->create([
            'title' => 'Selected Plaque Group',
            'description' => null,
            'order' => 1,
        ]);
        $matchingGroup = GenreGroup::query()->create([
            'title' => 'Searchable Plaque Group',
            'description' => null,
            'order' => 2,
        ]);
        $otherGroup = GenreGroup::query()->create([
            'title' => 'Other Plaque Group',
            'description' => null,
            'order' => 3,
        ]);
        $genre = Genre::resolveByTitle('Plaque Search Tag');
        $this->attachTagToGroup($selectedGroup, $genre, 1);

        Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->assertSet('editingTagGroupSearch', '')
            ->assertSet('editingTagGroupIds', [$selectedGroup->getKey()])
            ->assertSee('Search tag groups')
            ->assertSee('class="tag-library-modal-group-plaque"', false)
            ->assertSee('Selected Plaque Group')
            ->assertSee('wire:click="removeEditingTagGroup(' . $selectedGroup->getKey() . ')"', false)
            ->assertDontSee('wire:click="addEditingTagGroup(' . $matchingGroup->getKey() . ')"', false)
            ->assertDontSee('wire:click="addEditingTagGroup(' . $otherGroup->getKey() . ')"', false)
            ->set('editingTagGroupSearch', 'searchable')
            ->assertSee('class="tag-library-modal-group-dropdown"', false)
            ->assertSee('wire:click="addEditingTagGroup(' . $matchingGroup->getKey() . ')"', false)
            ->assertDontSee('wire:click="addEditingTagGroup(' . $otherGroup->getKey() . ')"', false)
            ->call('addEditingTagGroup', $matchingGroup->getKey())
            ->assertSet('editingTagGroupIds', [$selectedGroup->getKey(), $matchingGroup->getKey()])
            ->assertSet('editingTagGroupSearch', '')
            ->assertSee('Searchable Plaque Group')
            ->assertSee('wire:click="removeEditingTagGroup(' . $matchingGroup->getKey() . ')"', false)
            ->call('addEditingTagGroup', $matchingGroup->getKey())
            ->assertSet('editingTagGroupIds', [$selectedGroup->getKey(), $matchingGroup->getKey()])
            ->call('removeEditingTagGroup', $selectedGroup->getKey())
            ->assertSet('editingTagGroupIds', [$matchingGroup->getKey()])
            ->assertDontSee('wire:click="removeEditingTagGroup(' . $selectedGroup->getKey() . ')"', false);
    }

    public function test_tag_settings_group_search_empty_states_render_below_search(): void
    {
        $matchingGroup = GenreGroup::query()->create([
            'title' => 'Lonely Dropdown Group',
            'description' => null,
            'order' => 1,
        ]);
        $genre = Genre::resolveByTitle('No Group Plaque Tag');

        $blankHtml = Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->html();

        $this->assertStringContainsString('Search tag groups', $blankHtml);
        $this->assertStringContainsString('No groups assigned.', $blankHtml);
        $this->assertStringNotContainsString('wire:click="addEditingTagGroup(' . $matchingGroup->getKey() . ')"', $blankHtml);
        $this->assertLessThan(
            strpos($blankHtml, 'No groups assigned.'),
            strpos($blankHtml, 'Search tag groups'),
        );

        Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->set('editingTagGroupSearch', 'missing')
            ->assertSee('class="tag-library-modal-group-dropdown"', false)
            ->assertSee('No matching groups.')
            ->assertSee('No groups assigned.');
    }

    public function test_closing_tag_settings_discards_unsaved_group_plaque_changes(): void
    {
        $existingGroup = GenreGroup::query()->create([
            'title' => 'Existing Unsaved Group',
            'description' => null,
            'order' => 1,
        ]);
        $addedGroup = GenreGroup::query()->create([
            'title' => 'Added Unsaved Group',
            'description' => null,
            'order' => 2,
        ]);
        $genre = Genre::resolveByTitle('Unsaved Plaque Tag');
        $this->attachTagToGroup($existingGroup, $genre, 1);

        Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->call('addEditingTagGroup', $addedGroup->getKey())
            ->call('removeEditingTagGroup', $existingGroup->getKey())
            ->call('closeTagSettings')
            ->assertSet('editingTagGroupIds', [])
            ->assertSet('editingTagGroupSearch', '');

        $this->assertDatabaseHas('genre_group_genre', [
            'genre_group_id' => $existingGroup->getKey(),
            'genre_id' => $genre->getKey(),
        ]);
        $this->assertDatabaseMissing('genre_group_genre', [
            'genre_group_id' => $addedGroup->getKey(),
            'genre_id' => $genre->getKey(),
        ]);
    }

    public function test_saving_tag_settings_updates_hidden_state_and_group_memberships(): void
    {
        $keptGroup = GenreGroup::query()->create([
            'title' => 'Kept Settings Group',
            'description' => null,
            'order' => 1,
        ]);
        $removedGroup = GenreGroup::query()->create([
            'title' => 'Removed Settings Group',
            'description' => null,
            'order' => 2,
        ]);
        $addedGroup = GenreGroup::query()->create([
            'title' => 'Added Settings Group',
            'description' => null,
            'order' => 3,
        ]);
        $existingInAddedGroup = Genre::resolveByTitle('Existing Added Group Member');
        $genre = Genre::resolveByTitle('Settings Save Tag');

        $this->attachTagToGroup($keptGroup, $genre, 4);
        $this->attachTagToGroup($removedGroup, $genre, 2);
        $this->attachTagToGroup($addedGroup, $existingInAddedGroup, 7);

        Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->set('editingTagHidden', true)
            ->set('editingTagGroupIds', [$keptGroup->getKey(), $addedGroup->getKey()])
            ->call('saveTagSettings')
            ->assertSet('editingTagId', null)
            ->assertSee('Tag settings saved.');

        $this->assertDatabaseHas('genres', [
            'id' => $genre->getKey(),
            'hidden_on_index' => true,
        ]);
        $this->assertSame(4, $this->groupTagOrder($keptGroup, $genre));
        $this->assertDatabaseMissing('genre_group_genre', [
            'genre_group_id' => $removedGroup->getKey(),
            'genre_id' => $genre->getKey(),
        ]);
        $this->assertSame(8, $this->groupTagOrder($addedGroup, $genre));
    }

    public function test_tag_settings_saves_clears_and_validates_tag_color(): void
    {
        $genre = Genre::resolveByTitle('Colored Modal Tag');

        Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->assertSet('editingTagColor', '')
            ->assertSet('editingTagTextColor', '')
            ->assertSee('placeholder="#000000"', false)
            ->set('editingTagColor', '#AABBCC')
            ->set('editingTagTextColor', '#000000')
            ->call('saveTagSettings')
            ->assertHasNoErrors()
            ->assertSee('Tag settings saved.');

        $this->assertSame('#aabbcc', $genre->refresh()->color);
        $this->assertSame('#000000', $genre->refresh()->text_color);

        Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->assertSet('editingTagColor', '#aabbcc')
            ->assertSet('editingTagTextColor', '#000000')
            ->call('clearEditingTagColor')
            ->call('clearEditingTagTextColor')
            ->call('saveTagSettings')
            ->assertHasNoErrors();

        $this->assertNull($genre->refresh()->color);
        $this->assertNull($genre->refresh()->text_color);

        Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->set('editingTagColor', 'red')
            ->call('saveTagSettings')
            ->assertHasErrors(['editingTagColor']);

        Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->set('editingTagColor', '#112233')
            ->set('editingTagTextColor', 'blue')
            ->call('saveTagSettings')
            ->assertHasErrors(['editingTagTextColor']);
    }

    public function test_group_color_saves_clears_and_validates(): void
    {
        $group = GenreGroup::query()->create([
            'title' => 'Colored Settings Group',
            'description' => null,
            'order' => 1,
        ]);

        Livewire::test(TagLibraryManager::class)
            ->set("groupColors.{$group->getKey()}", '#CC3366')
            ->set("groupTextColors.{$group->getKey()}", '#000000')
            ->call('saveGroupColor', $group->getKey())
            ->assertHasNoErrors()
            ->assertSee('Tag group color saved.');

        $this->assertSame('#cc3366', $group->refresh()->color);
        $this->assertSame('#000000', $group->refresh()->text_color);

        Livewire::test(TagLibraryManager::class)
            ->call('clearGroupColor', $group->getKey())
            ->call('clearGroupTextColor', $group->getKey())
            ->assertHasNoErrors();

        $this->assertNull($group->refresh()->color);
        $this->assertNull($group->refresh()->text_color);

        Livewire::test(TagLibraryManager::class)
            ->set("groupColors.{$group->getKey()}", '#bad')
            ->call('saveGroupColor', $group->getKey())
            ->assertHasErrors(["groupColors.{$group->getKey()}"]);

        Livewire::test(TagLibraryManager::class)
            ->set("groupColors.{$group->getKey()}", '#112233')
            ->set("groupTextColors.{$group->getKey()}", 'black')
            ->call('saveGroupColor', $group->getKey())
            ->assertHasErrors(["groupTextColors.{$group->getKey()}"]);
    }

    public function test_tag_library_chips_use_group_color_over_tag_color_when_surface_is_enabled(): void
    {
        $plainFirstGroup = GenreGroup::query()->create([
            'title' => 'Plain First Color Group',
            'description' => null,
            'order' => 1,
            'text_color' => '#eeeeee',
        ]);
        $group = GenreGroup::query()->create([
            'title' => 'Color Override Group',
            'description' => null,
            'order' => 2,
            'color' => '#112233',
        ]);
        $genre = Genre::resolveByTitle('Color Override Tag');
        $genre->forceFill(['color' => '#445566', 'text_color' => '#111111'])->save();
        $this->attachTagToGroup($plainFirstGroup, $genre, 1);
        $this->attachTagToGroup($group, $genre, 1);

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->assertSee('tag-library-tag--background-colored', false)
            ->assertSee('tag-library-tag--text-colored', false)
            ->assertSee('--tag-color: #112233;', false)
            ->assertSee('--tag-text-color: #eeeeee;', false);

        Option::setTagColorSurfaces([Option::TAG_COLOR_SURFACE_TAG_LIBRARY => false]);

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->assertDontSee('tag-library-tag--background-colored', false)
            ->assertDontSee('tag-library-tag--text-colored', false)
            ->assertDontSee('--tag-color: #112233;', false)
            ->assertDontSee('--tag-text-color: #eeeeee;', false);
    }

    public function test_tag_library_background_and_font_colors_render_independently(): void
    {
        $backgroundOnly = Genre::resolveByTitle('Background Only Tag');
        $backgroundOnly->forceFill(['color' => '#112233', 'text_color' => null])->save();
        $fontOnly = Genre::resolveByTitle('Font Only Tag');
        $fontOnly->forceFill(['color' => null, 'text_color' => '#eeeeee'])->save();

        $component = Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags');

        $component
            ->assertSee('Background Only Tag')
            ->assertSee('Font Only Tag')
            ->assertSee('tag-library-tag--background-colored', false)
            ->assertSee('tag-library-tag--text-colored', false);

        $html = $component->html();

        $this->assertStringContainsString('--tag-color: #112233;', $html);
        $this->assertStringContainsString('--tag-text-color: #eeeeee;', $html);
    }

    public function test_tag_library_color_controls_use_background_color_label(): void
    {
        $genre = Genre::resolveByTitle('Label Color Tag');
        $group = GenreGroup::query()->create([
            'title' => 'Label Color Group',
            'description' => null,
            'order' => 1,
        ]);
        $this->attachTagToGroup($group, $genre, 1);

        Livewire::test(TagLibraryManager::class)
            ->call('openTagSettings', $genre->getKey())
            ->assertSee('Background color')
            ->assertSee('Group background color')
            ->assertDontSee('Chip color')
            ->assertDontSee('Group color');
    }

    public function test_tag_library_background_color_hover_does_not_override_font_color(): void
    {
        $css = file_get_contents(public_path('css/tag-library.css'));

        preg_match(
            '/\.tag-library-tag--background-colored:hover,\s*\.tag-library-tag--background-colored:focus\s*\{[^}]*}/',
            $css,
            $matches,
        );

        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('background-color:', $matches[0]);
        $this->assertDoesNotMatchRegularExpression('/(^|\n)\s*color\s*:/', $matches[0]);
    }

    public function test_tag_library_blade_does_not_embed_php_color_logic(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/tag-library-manager.blade.php'));

        $this->assertStringNotContainsString('@php', $blade);
        $this->assertStringNotContainsString('App\\Support\\TagColor', $blade);
        $this->assertStringNotContainsString('App\\Models\\Option', $blade);
    }

    public function test_all_tags_status_indicators_only_render_for_hidden_tags(): void
    {
        $hiddenGroup = GenreGroup::query()->create([
            'title' => 'Compact Hidden Group',
            'description' => null,
            'order' => 1,
            'hidden_on_index' => true,
        ]);
        $visibleGroup = GenreGroup::query()->create([
            'title' => 'Compact Visible Group',
            'description' => null,
            'order' => 2,
        ]);
        $ungrouped = Genre::resolveByTitle('Compact Ungrouped Tag');
        $hiddenTag = Genre::resolveByTitle('Compact Hidden Tag');
        $hiddenByGroup = Genre::resolveByTitle('Compact Hidden Group Tag');

        $hiddenTag->forceFill(['hidden_on_index' => true])->save();
        $this->attachTagToGroup($visibleGroup, $hiddenTag, 1);
        $this->attachTagToGroup($hiddenGroup, $hiddenByGroup, 1);

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->assertSee('aria-label="Hidden tag"', false)
            ->assertSee('class="tag-library-tag-status" aria-label="Hidden tag" title="Hidden tag"', false)
            ->assertDontSee('aria-label="Ungrouped tag"', false)
            ->assertDontSee('aria-label="Hidden by group"', false)
            ->assertDontSee('>Ungrouped</span>', false)
            ->assertDontSee('>Hidden tag</span>', false)
            ->assertDontSee('Compact Visible Group</span>', false);
    }

    public function test_hidden_status_indicator_renders_inside_tag_chip_between_title_and_count(): void
    {
        $visible = Genre::resolveByTitle('Inline Visible Tag');
        $hidden = Genre::resolveByTitle('Inline Hidden Tag');
        $hidden->forceFill(['hidden_on_index' => true])->save();

        $html = Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->html();

        $this->assertStringContainsString(
            '<span class="tag-library-tag-title">Inline Hidden Tag</span>',
            $html,
        );
        $this->assertStringContainsString(
            '<span class="tag-library-tag-status" aria-label="Hidden tag" title="Hidden tag">',
            $html,
        );
        $hiddenTitlePosition = strpos($html, '<span class="tag-library-tag-title">Inline Hidden Tag</span>');
        $hiddenStatusPosition = strpos($html, '<span class="tag-library-tag-status" aria-label="Hidden tag" title="Hidden tag">');
        $hiddenCountPosition = strpos($html, '<span class="tag-library-tag-count tag-library-tag-count--empty">0</span>', $hiddenStatusPosition);

        $this->assertIsInt($hiddenTitlePosition);
        $this->assertIsInt($hiddenStatusPosition);
        $this->assertIsInt($hiddenCountPosition);
        $this->assertLessThan($hiddenStatusPosition, $hiddenTitlePosition);
        $this->assertLessThan($hiddenCountPosition, $hiddenStatusPosition);
        $this->assertDoesNotMatchRegularExpression(
            '/<span class="tag-library-tag-title">Inline Visible Tag<\/span>\s*<span class="tag-library-tag-status"/s',
            $html,
        );
        $this->assertStringNotContainsString('aria-label="Ungrouped tag"', $html);
        $this->assertStringNotContainsString('aria-label="Hidden by group"', $html);
    }

    public function test_hidden_status_indicator_renders_when_any_assigned_group_is_hidden(): void
    {
        $hiddenGroup = GenreGroup::query()->create([
            'title' => 'Mixed Hidden Indicator Group',
            'description' => null,
            'order' => 1,
            'hidden_on_index' => true,
        ]);
        $visibleGroup = GenreGroup::query()->create([
            'title' => 'Mixed Visible Indicator Group',
            'description' => null,
            'order' => 2,
            'hidden_on_index' => false,
        ]);
        $tag = Genre::resolveByTitle('Mixed Group Hidden Indicator Tag');
        $tag->forceFill(['hidden_on_index' => false])->save();

        $this->attachTagToGroup($visibleGroup, $tag, 1);
        $this->attachTagToGroup($hiddenGroup, $tag, 2);

        $html = Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->html();

        $tagTitlePosition = strpos($html, '<span class="tag-library-tag-title">Mixed Group Hidden Indicator Tag</span>');
        $tagStatusPosition = strpos($html, '<span class="tag-library-tag-status" aria-label="Hidden tag" title="Hidden tag">', $tagTitlePosition);
        $tagCountPosition = strpos($html, '<span class="tag-library-tag-count tag-library-tag-count--empty">0</span>', $tagStatusPosition);

        $this->assertIsInt($tagTitlePosition);
        $this->assertIsInt($tagStatusPosition);
        $this->assertIsInt($tagCountPosition);
        $this->assertLessThan($tagStatusPosition, $tagTitlePosition);
        $this->assertLessThan($tagCountPosition, $tagStatusPosition);
    }

    public function test_empty_tag_delete_button_is_a_separate_circle_not_status_indicator(): void
    {
        $empty = Genre::resolveByTitle('Attached Delete Button Tag');

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->assertSee('class="tag-library-tag-shell tag-library-tag-shell--deletable"', false)
            ->assertSee('class="tag-library-delete-button"', false)
            ->assertDontSee('tag-library-delete-button--attached', false)
            ->assertSee('wire:click="askDeleteTag(' . $empty->getKey() . ')"', false);
    }

    public function test_all_tags_filters_visibility_group_status_specific_group_and_usage(): void
    {
        $hiddenGroup = GenreGroup::query()->create([
            'title' => 'Hidden Filter Group',
            'description' => null,
            'order' => 1,
            'hidden_on_index' => true,
        ]);
        $visibleGroup = GenreGroup::query()->create([
            'title' => 'Visible Filter Group',
            'description' => null,
            'order' => 2,
            'hidden_on_index' => false,
        ]);

        $visibleGrouped = Genre::resolveByTitle('Filter Visible Grouped');
        $hiddenByTag = Genre::resolveByTitle('Filter Hidden By Tag');
        $hiddenByGroup = Genre::resolveByTitle('Filter Hidden By Group');
        $mixedHiddenByGroup = Genre::resolveByTitle('Filter Mixed Hidden By Group');
        $ungrouped = Genre::resolveByTitle('Filter Ungrouped');
        $used = Genre::resolveByTitle('Filter Used');
        $product = Product::factory()->create();

        $visibleGrouped->forceFill(['hidden_on_index' => false])->save();
        $hiddenByTag->forceFill(['hidden_on_index' => true])->save();
        $hiddenByGroup->forceFill(['hidden_on_index' => false])->save();
        $mixedHiddenByGroup->forceFill(['hidden_on_index' => false])->save();
        $used->forceFill(['hidden_on_index' => false])->save();
        $this->attachTagToGroup($visibleGroup, $visibleGrouped, 1);
        $this->attachTagToGroup($visibleGroup, $hiddenByTag, 2);
        $this->attachTagToGroup($hiddenGroup, $hiddenByGroup, 1);
        $this->attachTagToGroup($visibleGroup, $mixedHiddenByGroup, 4);
        $this->attachTagToGroup($hiddenGroup, $mixedHiddenByGroup, 2);
        $this->attachTagToGroup($visibleGroup, $used, 3);
        app(ProductGenreSync::class)->syncCustom($product, [$used->getKey()]);

        $component = fn() => Livewire::test(TagLibraryManager::class)->call('toggleAllTags');

        $component()
            ->set('visibilityFilter', 'visible')
            ->assertSee('tag-library-tag-title">Filter Visible Grouped', false)
            ->assertDontSee('tag-library-tag-title">Filter Hidden By Tag', false)
            ->assertDontSee('tag-library-tag-title">Filter Hidden By Group', false)
            ->assertDontSee('tag-library-tag-title">Filter Mixed Hidden By Group', false);

        $component()
            ->set('visibilityFilter', 'hidden_tag')
            ->assertSee('tag-library-tag-title">Filter Hidden By Tag', false)
            ->assertDontSee('tag-library-tag-title">Filter Hidden By Group', false);

        $component()
            ->set('visibilityFilter', 'hidden_group')
            ->assertSee('tag-library-tag-title">Filter Hidden By Group', false)
            ->assertSee('tag-library-tag-title">Filter Mixed Hidden By Group', false)
            ->assertDontSee('tag-library-tag-title">Filter Hidden By Tag', false);

        $component()
            ->set('visibilityFilter', 'hidden_any')
            ->assertSee('tag-library-tag-title">Filter Hidden By Tag', false)
            ->assertSee('tag-library-tag-title">Filter Hidden By Group', false)
            ->assertSee('tag-library-tag-title">Filter Mixed Hidden By Group', false);

        $component()
            ->set('groupStatusFilter', 'ungrouped')
            ->assertSee('tag-library-tag-title">Filter Ungrouped', false)
            ->assertDontSee('tag-library-tag-title">Filter Visible Grouped', false);

        $component()
            ->set('groupStatusFilter', 'grouped')
            ->assertSee('tag-library-tag-title">Filter Visible Grouped', false)
            ->assertDontSee('tag-library-tag-title">Filter Ungrouped', false);

        $component()
            ->set('groupFilter', (string) $hiddenGroup->getKey())
            ->assertSee('tag-library-tag-title">Filter Hidden By Group', false)
            ->assertDontSee('tag-library-tag-title">Filter Visible Grouped', false);

        $component()
            ->set('usageFilter', 'empty')
            ->assertSee('tag-library-tag-title">Filter Visible Grouped', false)
            ->assertDontSee('tag-library-tag-title">Filter Used', false);

        $component()
            ->set('usageFilter', 'used')
            ->assertSee('tag-library-tag-title">Filter Used', false)
            ->assertDontSee('tag-library-tag-title">Filter Ungrouped', false);
    }

    public function test_grouped_all_tags_sort_uses_group_title_before_tag_title(): void
    {
        $alphaGroup = GenreGroup::query()->create([
            'title' => 'Alpha Sort Group',
            'description' => null,
            'order' => 1,
            'hidden_on_index' => false,
        ]);
        $betaGroup = GenreGroup::query()->create([
            'title' => 'Beta Sort Group',
            'description' => null,
            'order' => 1,
            'hidden_on_index' => false,
        ]);
        $alphaGroupTag = Genre::resolveByTitle('Sort Z Alpha Group Tag');
        $betaGroupTag = Genre::resolveByTitle('Sort A Beta Group Tag');

        $this->attachTagToGroup($alphaGroup, $alphaGroupTag, 1);
        $this->attachTagToGroup($betaGroup, $betaGroupTag, 1);

        Livewire::test(TagLibraryManager::class)
            ->call('toggleAllTags')
            ->set('groupStatusFilter', 'grouped')
            ->assertSeeInOrder([
                'Sort Z Alpha Group Tag',
                'Sort A Beta Group Tag',
            ]);
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

    private function groupTagOrder(GenreGroup $group, Genre $genre): int
    {
        return (int) DB::table('genre_group_genre')
            ->where('genre_group_id', $group->getKey())
            ->where('genre_id', $genre->getKey())
            ->value('order');
    }
}
