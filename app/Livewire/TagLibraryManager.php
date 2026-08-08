<?php

namespace App\Livewire;

use App\Enums\UiLanguage;
use App\Models\Genre;
use App\Models\GenreGroup;
use App\Models\Option;
use App\Support\GenreHierarchy;
use App\Support\ProductGenreSync;
use App\Support\TagColor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TagLibraryManager extends Component
{
    private const VISIBILITY_FILTERS = [
        'all',
        'visible',
        'hidden_tag',
        'hidden_group',
        'hidden_any',
    ];

    private const GROUP_STATUS_FILTERS = [
        'all',
        'grouped',
        'ungrouped',
    ];

    private const USAGE_FILTERS = [
        'all',
        'empty',
        'used',
    ];

    private const RELATIONSHIP_FILTERS = [
        'all',
        'related',
        'has_parent',
        'has_child',
        'both',
        'neither',
    ];

    private const COLOR_FILTERS = [
        'all',
        'customized',
        'default',
    ];

    private const SORT_FIELDS = [
        'alphabetical',
        'work_count',
    ];

    private const SORT_DIRECTIONS = [
        'asc',
        'desc',
    ];

    private const FILTER_DEFAULTS = [
        'visibilityFilter' => 'all',
        'groupStatusFilter' => 'all',
        'groupFilter' => 'all',
        'usageFilter' => 'all',
        'relationshipFilter' => 'all',
        'colorFilter' => 'all',
        'sortField' => 'alphabetical',
        'sortDirection' => 'asc',
    ];

    public string $search = '';

    public string $newTagTitle = '';

    public string $newGroupTitle = '';

    public bool $showAllTags = false;

    public ?int $confirmingDeleteTagId = null;

    public ?int $confirmingDeleteGroupId = null;

    public string $notice = '';

    public int $noticeId = 0;

    public array $groupTitles = [];

    public array $groupHidden = [];

    public array $groupColors = [];

    public array $groupTextColors = [];

    public array $tagHidden = [];

    public array $groupTagInputs = [];

    public string $visibilityFilter = 'all';

    public string $groupStatusFilter = 'all';

    public string $groupFilter = 'all';

    public string $usageFilter = 'all';

    public string $relationshipFilter = 'all';

    public string $colorFilter = 'all';

    public string $sortField = 'alphabetical';

    public string $sortDirection = 'asc';

    public array $filterDraft = self::FILTER_DEFAULTS;

    public bool $tagEditMode = false;

    public bool $indexGroupOrderingEnabled = false;

    #[Locked]
    public ?int $editingTagId = null;

    public string $editingTagTitle = '';

    public bool $editingTagHidden = false;

    public string $editingTagColor = '';

    public string $editingTagTextColor = '';

    public array $editingTagGroupIds = [];

    public string $editingTagGroupSearch = '';

    public array $editingTagParentIds = [];

    public string $editingTagParentSearch = '';

    public array $editingTagChildIds = [];

    public string $editingTagChildSearch = '';

    protected function rules(): array
    {
        return [
            'newTagTitle' => ['required', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'newTagTitle.required' => __('Enter a tag title.'),
            'newTagTitle.max' => __('Tag titles may not be greater than 255 characters.'),
        ];
    }

    public function mount(): void
    {
        $this->showAllTags = Option::tagLibraryTagsExpandedByDefault();
        $this->indexGroupOrderingEnabled = Option::tagLibraryIndexGroupOrderingEnabled();
        $this->syncFilterDraft();
    }

    public function render(GenreHierarchy $hierarchy): View
    {
        $this->normalizeFilters();
        $groups = $this->groups();
        $this->syncGroupState($groups);
        $tagLibraryColorsEnabled = Option::tagColorSurfaceEnabled(Option::TAG_COLOR_SURFACE_TAG_LIBRARY);

        return view('livewire.tag-library-manager', [
            'genres' => $this->visibleGenres($tagLibraryColorsEnabled),
            'groups' => $this->groupViewData($groups, $tagLibraryColorsEnabled),
            'groupOptions' => $this->groupOptions($groups),
            'confirmingDeleteTag' => $this->confirmingDeleteTag(),
            'confirmingDeleteGroup' => $this->confirmingDeleteGroup(),
            'editingTag' => $this->editingTag(),
            'editingSelectedGroupOptions' => $this->editingSelectedGroupOptions($groups),
            'editingAvailableGroupOptions' => $this->editingAvailableGroupOptions($groups),
            'editingSelectedParentOptions' => $this->editingSelectedRelationshipOptions($this->editingTagParentIds),
            'editingAvailableParentOptions' => $this->editingAvailableParentOptions($hierarchy),
            'editingSelectedChildOptions' => $this->editingSelectedRelationshipOptions($this->editingTagChildIds),
            'editingAvailableChildOptions' => $this->editingAvailableChildOptions($hierarchy),
            'fetchedLanguageCode' => UiLanguage::current()->fetchedTagCode(),
            'filtersActive' => $this->filtersAreActive(),
        ]);
    }

    public function updated(string $property): void
    {
        if (! array_key_exists($property, self::FILTER_DEFAULTS)) {
            return;
        }

        $this->normalizeFilters();
        $this->syncFilterDraft();
        $this->showAllTags = true;
        $this->clearNotice();
    }

    public function updatedSearch(): void
    {
        $this->clearNotice();

        if (filled($this->search)) {
            $this->showAllTags = true;
        }
    }

    public function updatedTagEditMode(): void
    {
        if (! $this->tagEditMode) {
            $this->closeTagSettings();
        }

        $this->clearNotice();
    }

    public function updatedIndexGroupOrderingEnabled(): void
    {
        Option::setTagLibraryIndexGroupOrderingEnabled($this->indexGroupOrderingEnabled);
        $this->indexGroupOrderingEnabled = Option::tagLibraryIndexGroupOrderingEnabled();
        $this->setNotice(
            $this->indexGroupOrderingEnabled
                ? 'Index group ordering enabled.'
                : 'Index group ordering disabled.'
        );
    }

    public function toggleAllTags(): void
    {
        $this->showAllTags = ! $this->showAllTags;
        $this->clearNotice();
    }

    public function applyFilters(): void
    {
        $this->assignFilterState($this->normalizedFilterState($this->filterDraft));
        $this->syncFilterDraft();
        $this->showAllTags = true;
        $this->clearNotice();
    }

    public function clearFilters(): void
    {
        $this->reset(array_keys(self::FILTER_DEFAULTS));
        $this->syncFilterDraft();
        $this->showAllTags = true;
        $this->clearNotice();
    }

    public function createTag(): void
    {
        $validated = $this->validateOnly(
            'newTagTitle',
            dataOverrides: ['newTagTitle' => trim($this->newTagTitle)],
        );
        $title = $validated['newTagTitle'];

        $genre = Genre::resolveByTitle($title);

        $this->newTagTitle = '';
        $this->search = $genre->wasRecentlyCreated ? '' : $genre->title;
        $this->showAllTags = true;
        $this->setNotice($genre->wasRecentlyCreated ? 'Tag created.' : 'Tag already exists.');
    }

    public function askDeleteTag(int $genreId): void
    {
        $this->clearNotice();

        if (! $this->isEmptyTag($genreId)) {
            $this->confirmingDeleteTagId = null;
            $this->setNotice('Only empty tags can be deleted.');

            return;
        }

        $this->confirmingDeleteTagId = $genreId;
    }

    public function cancelDeleteTag(): void
    {
        $this->confirmingDeleteTagId = null;
    }

    public function deleteTag(): void
    {
        if ($this->confirmingDeleteTagId === null) {
            return;
        }

        $genreId = $this->confirmingDeleteTagId;
        $this->confirmingDeleteTagId = null;

        $deleted = Genre::query()
            ->whereKey($genreId)
            ->whereDoesntHave('products')
            ->delete();

        if ($deleted === 0) {
            $this->setNotice('Only empty tags can be deleted.');

            return;
        }

        $this->setNotice('Empty tag deleted.');
    }

    public function createGroup(): void
    {
        $title = $this->validatedTitle($this->newGroupTitle, 'newGroupTitle', __('group title'), 255);

        Validator::make(
            ['newGroupTitle' => $title],
            ['newGroupTitle' => [Rule::unique(GenreGroup::class, 'title')]],
            ['newGroupTitle.unique' => __('Tag group title already exists.')],
        )->validate();

        GenreGroup::query()->create(['title' => $title]);

        $this->newGroupTitle = '';
        $this->setNotice('Tag group created.');
    }

    public function renameGroup(int $groupId): void
    {
        $group = GenreGroup::query()->find($groupId);

        if (! $group) {
            return;
        }

        $title = $this->validatedTitle(
            $this->groupTitles[$groupId] ?? '',
            "groupTitles.{$groupId}",
            __('group title'),
            255,
        );

        if (Validator::make(
            ['title' => $title],
            ['title' => [Rule::unique(GenreGroup::class, 'title')->ignore($group)]],
        )->fails()) {
            $this->setNotice('Tag group title already exists.');
            $this->groupTitles[$groupId] = $group->title;

            return;
        }

        $group->forceFill(['title' => $title])->save();
        $this->groupTitles[$groupId] = $title;
        $this->setNotice('Tag group renamed.');
    }

    public function askDeleteGroup(int $groupId): void
    {
        $this->clearNotice();
        $this->confirmingDeleteGroupId = GenreGroup::query()->whereKey($groupId)->exists()
            ? $groupId
            : null;
    }

    public function cancelDeleteGroup(): void
    {
        $this->confirmingDeleteGroupId = null;
    }

    public function deleteGroup(): void
    {
        if ($this->confirmingDeleteGroupId === null) {
            return;
        }

        $groupId = $this->confirmingDeleteGroupId;
        $this->confirmingDeleteGroupId = null;

        GenreGroup::destroy($groupId);

        unset(
            $this->groupTitles[$groupId],
            $this->groupHidden[$groupId],
            $this->groupColors[$groupId],
            $this->groupTextColors[$groupId],
            $this->groupTagInputs[$groupId],
        );

        $this->setNotice('Tag group deleted.');
    }

    public function addTagToGroup(int $groupId): void
    {
        $group = GenreGroup::query()->find($groupId);

        if (! $group) {
            return;
        }

        $title = $this->validatedTitle(
            $this->groupTagInputs[$groupId] ?? '',
            "groupTagInputs.{$groupId}",
            __('tag title'),
            255,
        );

        $genre = Genre::resolveByTitle($title);

        if ($group->genres()->whereKey($genre->getKey())->exists()) {
            $this->groupTagInputs[$groupId] = '';
            $this->tagHidden[$genre->getKey()] = (bool) $genre->hidden_on_index;
            $this->setNotice('Tag is already in this group.');

            return;
        }

        $group->genres()->attach($genre->getKey(), [
            'order' => $this->nextGroupTagOrder($group->getKey()),
        ]);

        $this->groupTagInputs[$groupId] = '';
        $this->tagHidden[$genre->getKey()] = (bool) $genre->hidden_on_index;
        $this->setNotice('Tag added to group.');
    }

    public function removeTagFromGroup(int $groupId, int $genreId): void
    {
        $group = GenreGroup::query()->find($groupId);

        if (! $group) {
            return;
        }

        $group->genres()->detach($genreId);

        $this->setNotice('Tag removed from group.');
    }

    public function saveGroupHidden(int $groupId): void
    {
        $group = GenreGroup::query()->find($groupId);

        if (! $group) {
            return;
        }

        $group->forceFill([
            'hidden_on_index' => (bool) ($this->groupHidden[$groupId] ?? false),
        ])->save();

        $this->setNotice('Tag group visibility saved.');
    }

    public function saveGroupColor(int $groupId): void
    {
        $group = GenreGroup::query()->find($groupId);

        if (! $group) {
            return;
        }

        $color = $this->validatedColor($this->groupColors[$groupId] ?? '', "groupColors.{$groupId}");
        $textColor = $this->validatedColor($this->groupTextColors[$groupId] ?? '', "groupTextColors.{$groupId}");

        $group->forceFill([
            'color' => $color,
            'text_color' => $textColor,
        ])->save();

        $this->groupColors[$groupId] = $color ?? '';
        $this->groupTextColors[$groupId] = $textColor ?? '';
        $this->setNotice('Tag group color saved.');
    }

    public function clearGroupColor(int $groupId): void
    {
        $this->groupColors[$groupId] = '';
        $this->saveGroupColor($groupId);
    }

    public function clearGroupTextColor(int $groupId): void
    {
        $this->groupTextColors[$groupId] = '';
        $this->saveGroupColor($groupId);
    }

    public function saveTagHidden(int $genreId): void
    {
        $genre = Genre::query()->find($genreId);

        if (! $genre) {
            return;
        }

        $genre->forceFill([
            'hidden_on_index' => (bool) ($this->tagHidden[$genreId] ?? false),
        ])->save();

        $this->setNotice('Tag visibility saved.');
    }

    public function openTagSettings(int $genreId): void
    {
        $genre = Genre::query()
            ->with(['groups', 'parents', 'children'])
            ->find($genreId);

        if (! $genre) {
            $this->closeTagSettings();

            return;
        }

        $this->tagEditMode = true;
        $this->editingTagId = $genre->getKey();
        $this->editingTagTitle = $genre->title;
        $this->editingTagHidden = (bool) $genre->hidden_on_index;
        $this->editingTagColor = $genre->color ?? '';
        $this->editingTagTextColor = $genre->text_color ?? '';
        $this->editingTagGroupIds = $genre->groups->modelKeys();
        $this->editingTagGroupSearch = '';
        $this->editingTagParentIds = $genre->parents->modelKeys();
        $this->editingTagParentSearch = '';
        $this->editingTagChildIds = $genre->children->modelKeys();
        $this->editingTagChildSearch = '';
        $this->clearNotice();
    }

    public function closeTagSettings(): void
    {
        $this->reset([
            'editingTagId',
            'editingTagTitle',
            'editingTagHidden',
            'editingTagColor',
            'editingTagTextColor',
            'editingTagGroupIds',
            'editingTagGroupSearch',
            'editingTagParentIds',
            'editingTagParentSearch',
            'editingTagChildIds',
            'editingTagChildSearch',
        ]);
    }

    public function addEditingTagGroup(int $groupId): void
    {
        if (! GenreGroup::query()->whereKey($groupId)->exists()) {
            return;
        }

        $groupIds = $this->normalizedEditingGroupIds();

        if (! $groupIds->contains($groupId)) {
            $groupIds->push($groupId);
        }

        $this->editingTagGroupIds = $groupIds->all();
        $this->editingTagGroupSearch = '';
    }

    public function removeEditingTagGroup(int $groupId): void
    {
        $this->editingTagGroupIds = $this->normalizedEditingGroupIds()
            ->reject(fn(int $currentGroupId): bool => $currentGroupId === $groupId)
            ->values()
            ->all();
    }

    public function addEditingTagParent(int $genreId): void
    {
        $this->addEditingRelationshipTag(
            $genreId,
            $this->editingTagParentIds,
            $this->editingTagChildIds,
        );
        $this->editingTagParentSearch = '';
    }

    public function removeEditingTagParent(int $genreId): void
    {
        $this->editingTagParentIds = $this->withoutRelationshipTag(
            $this->editingTagParentIds,
            $genreId,
        );
    }

    public function addEditingTagChild(int $genreId): void
    {
        $this->addEditingRelationshipTag(
            $genreId,
            $this->editingTagChildIds,
            $this->editingTagParentIds,
        );
        $this->editingTagChildSearch = '';
    }

    public function removeEditingTagChild(int $genreId): void
    {
        $this->editingTagChildIds = $this->withoutRelationshipTag(
            $this->editingTagChildIds,
            $genreId,
        );
    }

    public function saveTagSettings(
        GenreHierarchy $hierarchy,
        ProductGenreSync $genreSync,
    ): void {
        if ($this->editingTagId === null) {
            return;
        }

        $genreId = $this->editingTagId;
        $title = trim($this->editingTagTitle);
        Validator::make(
            ['editingTagTitle' => $title],
            ['editingTagTitle' => ['required', 'string', 'max:255']],
            [
                'editingTagTitle.required' => __('Enter a tag title.'),
                'editingTagTitle.max' => __('Tag titles may not be greater than 255 characters.'),
            ],
        )->validate();
        $hiddenOnIndex = $this->editingTagHidden;
        $color = $this->validatedColor($this->editingTagColor, 'editingTagColor');
        $textColor = $this->validatedColor($this->editingTagTextColor, 'editingTagTextColor');
        $targetGroupIds = $this->validEditingGroupIds();
        $targetParentIds = $this->validEditingRelationshipIds($this->editingTagParentIds);
        $targetChildIds = $this->validEditingRelationshipIds($this->editingTagChildIds);
        $saved = false;

        DB::transaction(function () use (
            $genreId,
            $title,
            $hiddenOnIndex,
            $color,
            $textColor,
            $targetGroupIds,
            $targetParentIds,
            $targetChildIds,
            $hierarchy,
            $genreSync,
            &$saved,
        ): void {
            $genre = Genre::query()
                ->lockForUpdate()
                ->find($genreId);

            if (! $genre) {
                return;
            }

            Validator::make(
                ['editingTagTitle' => Genre::titleKey($title)],
                [
                    'editingTagTitle' => [
                        Rule::unique(Genre::class, 'title_key')->ignore($genre),
                    ],
                ],
                ['editingTagTitle.unique' => __('Tag title already exists.')],
            )->validate();

            $genre->forceFill([
                'title' => $title,
                'hidden_on_index' => $hiddenOnIndex,
                'color' => $color,
                'text_color' => $textColor,
            ])->save();

            $currentGroupIds = $genre->groups()
                ->pluck('genre_groups.id')
                ->map(fn($groupId): int => (int) $groupId);

            $groupPivots = collect($targetGroupIds)
                ->mapWithKeys(fn(int $groupId): array => [
                    $groupId => $currentGroupIds->contains($groupId)
                        ? []
                        : ['order' => $this->nextGroupTagOrder($groupId)],
                ])
                ->all();

            $genre->groups()->sync($groupPivots);
            $affectedChildIds = $hierarchy->sync($genre, $targetParentIds, $targetChildIds);
            $genreSync->syncParentsForProductsWithAny($affectedChildIds);

            $saved = true;
        });

        if (! $saved) {
            $this->closeTagSettings();
            $this->setNotice('Tag no longer exists.');

            return;
        }

        $this->tagHidden[$genreId] = $hiddenOnIndex;
        $this->closeTagSettings();
        $this->setNotice('Tag settings saved.');
    }

    public function clearEditingTagColor(): void
    {
        $this->editingTagColor = '';
    }

    public function clearEditingTagTextColor(): void
    {
        $this->editingTagTextColor = '';
    }

    public function moveGroup(int $groupId, int $direction): void
    {
        $ids = $this->moveOrderedIds($this->orderedGroups()->pluck('id'), $groupId, $direction);

        if ($ids !== null) {
            $this->saveGroupOrder($ids);
        }
    }

    public function reorderGroups(string $item, int $position): void
    {
        $ids = $this->reorderIds($this->orderedGroups()->pluck('id'), (int) $item, $position);

        if ($ids !== null) {
            $this->saveGroupOrder($ids);
        }
    }

    public function moveGroupTag(int $groupId, int $genreId, int $direction): void
    {
        $ids = $this->moveOrderedIds($this->orderedTagsInGroup($groupId)->pluck('id'), $genreId, $direction);

        if ($ids !== null) {
            $this->saveGroupTagOrder($groupId, $ids);
        }
    }

    public function reorderGroupTags(string $item, int $position): void
    {
        [$groupId, $genreId] = $this->groupTagItemIds($item);

        if ($groupId === null || $genreId === null) {
            return;
        }

        $ids = $this->reorderIds($this->orderedTagsInGroup($groupId)->pluck('id'), $genreId, $position);

        if ($ids !== null) {
            $this->saveGroupTagOrder($groupId, $ids);
        }
    }

    /**
     * @return Collection<int, object{id: int, title: string, products_count: int, pivot_count: int, group_title: string, hidden_on_index: bool, group_hidden_on_index: bool, hidden_on_index_effective: bool, color: ?string, text_color: ?string, color_style: string, has_background_color: bool, has_font_color: bool}>
     */
    private function visibleGenres(bool $colorsEnabled): Collection
    {
        $specificGroupId = $this->specificGroupFilterId();

        return $this->visibleGenreQuery()
            ->get()
            ->map(fn(Genre $genre): object => $this->genreViewData($genre, $specificGroupId, $colorsEnabled));
    }

    private function visibleGenreQuery(): Builder
    {
        $search = trim($this->search);
        $specificGroupId = $this->specificGroupFilterId();

        return Genre::query()
            ->with('groups')
            ->withCount([
                'products as pivot_count',
                'visibleProducts as products_count',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('genres.title', 'like', '%' . $search . '%');
            })
            ->when($this->visibilityFilter === 'visible', function ($query): void {
                $query->visibleOnIndex();
            })
            ->when($this->visibilityFilter === 'hidden_tag', function ($query): void {
                $query->where('genres.hidden_on_index', true);
            })
            ->when($this->visibilityFilter === 'hidden_group', function ($query): void {
                $query->whereHas('groups', function (Builder $group): void {
                    $group->hiddenOnIndex();
                });
            })
            ->when($this->visibilityFilter === 'hidden_any', function ($query): void {
                $query->hiddenOnIndex();
            })
            ->when($this->groupStatusFilter === 'grouped', function ($query): void {
                $query->has('groups');
            })
            ->when($this->groupStatusFilter === 'ungrouped', function ($query): void {
                $query->doesntHave('groups');
            })
            ->when($specificGroupId !== null, function ($query) use ($specificGroupId): void {
                $query->whereHas('groups', function (Builder $group) use ($specificGroupId): void {
                    $group->whereKey($specificGroupId);
                });
            })
            ->where(function (Builder $query): void {
                $query->whereHas('visibleProducts')
                    ->orWhereDoesntHave('products');
            })
            ->when($this->usageFilter === 'empty', function ($query): void {
                $query->doesntHave('products');
            })
            ->when($this->usageFilter === 'used', function ($query): void {
                $query->has('products');
            })
            ->when($this->relationshipFilter === 'related', function ($query): void {
                $query->where(function (Builder $relations): void {
                    $relations->has('parents')->orHas('children');
                });
            })
            ->when($this->relationshipFilter === 'has_parent', function ($query): void {
                $query->has('parents');
            })
            ->when($this->relationshipFilter === 'has_child', function ($query): void {
                $query->has('children');
            })
            ->when($this->relationshipFilter === 'both', function ($query): void {
                $query->has('parents')->has('children');
            })
            ->when($this->relationshipFilter === 'neither', function ($query): void {
                $query->doesntHave('parents')->doesntHave('children');
            })
            ->when($this->colorFilter === 'customized', function ($query): void {
                $query->where(function (Builder $colors): void {
                    $colors->whereNotNull('genres.color')->orWhereNotNull('genres.text_color');
                });
            })
            ->when($this->colorFilter === 'default', function ($query): void {
                $query->whereNull('genres.color')->whereNull('genres.text_color');
            })
            ->when(
                $this->sortField === 'work_count',
                fn(Builder $query): Builder => $query
                    ->orderBy('products_count', $this->sortDirection)
                    ->orderBy('genres.title')
                    ->orderBy('genres.id'),
                fn(Builder $query): Builder => $query
                    ->orderBy('genres.title', $this->sortDirection)
                    ->orderBy('genres.id', $this->sortDirection),
            );
    }

    private function specificGroupFilterId(): ?int
    {
        return $this->groupFilter !== 'all' && ctype_digit($this->groupFilter)
            ? (int) $this->groupFilter
            : null;
    }

    private function genreViewData(Genre $genre, ?int $specificGroupId, bool $colorsEnabled): object
    {
        $groups = $specificGroupId === null
            ? $genre->groups
            : $genre->groups->where('id', $specificGroupId)->values();

        $hasHiddenGroup = $genre->groups
            ->contains(fn(GenreGroup $group): bool => (bool) $group->hidden_on_index);
        $colors = $colorsEnabled
            ? $this->effectiveGenreColors($genre, $groups)
            : TagColor::viewData(null, null);

        return (object) [
            'id' => $genre->getKey(),
            'title' => $genre->title,
            'products_count' => (int) $genre->products_count,
            'pivot_count' => (int) $genre->pivot_count,
            'group_title' => $groups->pluck('title')->implode(', '),
            'hidden_on_index' => (bool) $genre->hidden_on_index,
            'group_hidden_on_index' => $hasHiddenGroup,
            'hidden_on_index_effective' => (bool) $genre->hidden_on_index || $hasHiddenGroup,
            ...$colors,
        ];
    }

    private function groupViewData(Collection $groups, bool $colorsEnabled): Collection
    {
        return $groups
            ->map(function (GenreGroup $group) use ($colorsEnabled): object {
                $groupColors = $colorsEnabled
                    ? TagColor::viewData($group->color, $group->text_color)
                    : TagColor::viewData(null, null);

                return (object) [
                    'id' => $group->getKey(),
                    'title' => $group->title,
                    'hidden_on_index' => (bool) $group->hidden_on_index,
                    ...$groupColors,
                    'genres' => $group->genres
                        ->map(fn(Genre $genre): object => $this->groupGenreViewData($group, $genre, $colorsEnabled))
                        ->values(),
                ];
            })
            ->values();
    }

    private function groupGenreViewData(GenreGroup $group, Genre $genre, bool $colorsEnabled): object
    {
        $colors = $colorsEnabled
            ? TagColor::viewData(
                TagColor::normalize($group->color) ?? TagColor::normalize($genre->color),
                TagColor::normalize($group->text_color) ?? TagColor::normalize($genre->text_color),
            )
            : TagColor::viewData(null, null);

        return (object) [
            'id' => $genre->getKey(),
            'title' => $genre->title,
            'products_count' => (int) ($genre->products_count ?? 0),
            ...$colors,
        ];
    }

    private function effectiveGenreColors(Genre $genre, Collection $groups): array
    {
        $groupColors = TagColor::firstGroupColorPair($groups);

        return TagColor::viewData(
            $groupColors['color'] ?? TagColor::normalize($genre->color),
            $groupColors['text_color'] ?? TagColor::normalize($genre->text_color),
        );
    }

    private function confirmingDeleteTag(): ?Genre
    {
        if ($this->confirmingDeleteTagId === null) {
            return null;
        }

        return Genre::query()->find($this->confirmingDeleteTagId);
    }

    private function confirmingDeleteGroup(): ?GenreGroup
    {
        if ($this->confirmingDeleteGroupId === null) {
            return null;
        }

        return GenreGroup::query()->find($this->confirmingDeleteGroupId);
    }

    private function editingTag(): ?Genre
    {
        if ($this->editingTagId === null) {
            return null;
        }

        return Genre::query()
            ->with('groups')
            ->withCount('products')
            ->find($this->editingTagId);
    }

    private function isEmptyTag(int $genreId): bool
    {
        return Genre::query()
            ->whereKey($genreId)
            ->whereDoesntHave('products')
            ->exists();
    }

    private function groups(): Collection
    {
        return $this->orderedGroups()
            ->load(['genres' => function ($query): void {
                $query
                    ->withCount('visibleProducts as products_count')
                    ->where(function (Builder $query): void {
                        $query->whereHas('visibleProducts')
                            ->orWhereDoesntHave('products');
                    });
            }]);
    }

    private function groupOptions(Collection $groups): Collection
    {
        return $groups
            ->map(fn(GenreGroup $group): array => [
                'id' => $group->getKey(),
                'title' => $group->title,
            ]);
    }

    private function editingSelectedGroupOptions(Collection $groups): Collection
    {
        $orderedGroups = $groups->keyBy(fn(GenreGroup $group): int => $group->getKey());

        return $this->normalizedEditingGroupIds()
            ->map(fn(int $groupId): ?GenreGroup => $orderedGroups->get($groupId))
            ->filter()
            ->map(fn(GenreGroup $group): array => [
                'id' => $group->getKey(),
                'title' => $group->title,
            ])
            ->values();
    }

    private function editingAvailableGroupOptions(Collection $groups): Collection
    {
        $selectedGroupIds = $this->normalizedEditingGroupIds();
        $search = mb_strtolower(trim($this->editingTagGroupSearch));

        if ($search === '') {
            return collect();
        }

        return $groups
            ->reject(fn(GenreGroup $group): bool => $selectedGroupIds->contains($group->getKey()))
            ->filter(fn(GenreGroup $group): bool => str_contains(mb_strtolower($group->title), $search))
            ->map(fn(GenreGroup $group): array => [
                'id' => $group->getKey(),
                'title' => $group->title,
            ])
            ->values();
    }

    private function editingSelectedRelationshipOptions(array $genreIds): Collection
    {
        if ($this->editingTagId === null) {
            return collect();
        }

        return Genre::query()
            ->whereKey($this->normalizedEditingRelationshipIds($genreIds))
            ->orderBy('title')
            ->orderBy('id')
            ->get(['id', 'title'])
            ->map(fn(Genre $genre): array => [
                'id' => $genre->getKey(),
                'title' => $genre->title,
            ]);
    }

    private function editingAvailableParentOptions(GenreHierarchy $hierarchy): Collection
    {
        if ($this->editingTagId === null || blank($this->editingTagParentSearch)) {
            return collect();
        }

        $excludedIds = [
            ...$this->editingTagChildIds,
            ...$hierarchy->descendantIds([$this->editingTagId]),
        ];

        return $this->editingAvailableRelationshipOptions(
            $this->editingTagParentSearch,
            $this->editingTagParentIds,
            $excludedIds,
        );
    }

    private function editingAvailableChildOptions(GenreHierarchy $hierarchy): Collection
    {
        if ($this->editingTagId === null || blank($this->editingTagChildSearch)) {
            return collect();
        }

        $excludedIds = [
            ...$this->editingTagParentIds,
            ...$hierarchy->ancestorIds([$this->editingTagId]),
        ];

        return $this->editingAvailableRelationshipOptions(
            $this->editingTagChildSearch,
            $this->editingTagChildIds,
            $excludedIds,
        );
    }

    private function editingAvailableRelationshipOptions(
        string $search,
        array $selectedIds,
        array $excludedIds,
    ): Collection {
        $search = trim($search);

        if ($this->editingTagId === null || $search === '') {
            return collect();
        }

        $excludedIds = collect([
            $this->editingTagId,
            ...$selectedIds,
            ...$excludedIds,
        ])
            ->map(fn($genreId): int => (int) $genreId)
            ->unique()
            ->all();

        return Genre::query()
            ->whereKeyNot($excludedIds)
            ->whereLike('title', '%' . $search . '%')
            ->orderBy('title')
            ->orderBy('id')
            ->limit(25)
            ->get(['id', 'title'])
            ->map(fn(Genre $genre): array => [
                'id' => $genre->getKey(),
                'title' => $genre->title,
            ]);
    }

    private function addEditingRelationshipTag(int $genreId, array &$selectedIds, array $oppositeIds): void
    {
        if (
            $this->editingTagId === null
            || $genreId === $this->editingTagId
            || in_array($genreId, $oppositeIds, true)
            || ! Genre::query()->whereKey($genreId)->exists()
        ) {
            return;
        }

        $selectedIds = collect($selectedIds)
            ->push($genreId)
            ->map(fn($selectedId): int => (int) $selectedId)
            ->unique()
            ->values()
            ->all();
    }

    private function withoutRelationshipTag(array $genreIds, int $removedGenreId): array
    {
        return collect($genreIds)
            ->map(fn($genreId): int => (int) $genreId)
            ->reject(fn(int $genreId): bool => $genreId === $removedGenreId)
            ->values()
            ->all();
    }

    private function syncGroupState(Collection $groups): void
    {
        foreach ($groups as $group) {
            $groupId = $group->getKey();

            if (! array_key_exists($groupId, $this->groupTitles)) {
                $this->groupTitles[$groupId] = $group->title;
            }

            if (! array_key_exists($groupId, $this->groupHidden)) {
                $this->groupHidden[$groupId] = (bool) $group->hidden_on_index;
            }

            if (! array_key_exists($groupId, $this->groupColors)) {
                $this->groupColors[$groupId] = $group->color ?? '';
            }

            if (! array_key_exists($groupId, $this->groupTextColors)) {
                $this->groupTextColors[$groupId] = $group->text_color ?? '';
            }

            if (! array_key_exists($groupId, $this->groupTagInputs)) {
                $this->groupTagInputs[$groupId] = '';
            }

            foreach ($group->genres as $genre) {
                $genreId = $genre->getKey();

                if (! array_key_exists($genreId, $this->tagHidden)) {
                    $this->tagHidden[$genreId] = (bool) $genre->hidden_on_index;
                }
            }
        }
    }

    private function normalizeFilters(): void
    {
        $this->assignFilterState($this->normalizedFilterState($this->currentFilterState()));
    }

    private function syncFilterDraft(): void
    {
        $this->filterDraft = $this->currentFilterState();
    }

    /**
     * @return array<string, string>
     */
    private function currentFilterState(): array
    {
        $state = [];

        foreach (array_keys(self::FILTER_DEFAULTS) as $property) {
            $state[$property] = $this->{$property};
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, string>
     */
    private function normalizedFilterState(array $state): array
    {
        return [
            'visibilityFilter' => $this->normalizedChoice(
                $state['visibilityFilter'] ?? null,
                self::VISIBILITY_FILTERS,
            ),
            'groupStatusFilter' => $this->normalizedChoice(
                $state['groupStatusFilter'] ?? null,
                self::GROUP_STATUS_FILTERS,
            ),
            'groupFilter' => $this->normalizedGroupFilter($state['groupFilter'] ?? null),
            'usageFilter' => $this->normalizedChoice(
                $state['usageFilter'] ?? null,
                self::USAGE_FILTERS,
            ),
            'relationshipFilter' => $this->normalizedChoice(
                $state['relationshipFilter'] ?? null,
                self::RELATIONSHIP_FILTERS,
            ),
            'colorFilter' => $this->normalizedChoice(
                $state['colorFilter'] ?? null,
                self::COLOR_FILTERS,
            ),
            'sortField' => $this->normalizedChoice(
                $state['sortField'] ?? null,
                self::SORT_FIELDS,
                'alphabetical',
            ),
            'sortDirection' => $this->normalizedChoice(
                $state['sortDirection'] ?? null,
                self::SORT_DIRECTIONS,
                'asc',
            ),
        ];
    }

    /**
     * @param  array<string, string>  $state
     */
    private function assignFilterState(array $state): void
    {
        foreach (array_keys(self::FILTER_DEFAULTS) as $property) {
            $this->{$property} = $state[$property];
        }
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function normalizedChoice(mixed $value, array $allowed, string $default = 'all'): string
    {
        return is_string($value) && in_array($value, $allowed, true)
            ? $value
            : $default;
    }

    private function normalizedGroupFilter(mixed $value): string
    {
        if ($value === 'all') {
            return 'all';
        }

        if ((! is_string($value) && ! is_int($value)) || ! ctype_digit((string) $value)) {
            return 'all';
        }

        $groupId = (int) $value;

        return $groupId > 0 && GenreGroup::query()->whereKey($groupId)->exists()
            ? (string) $groupId
            : 'all';
    }

    private function filtersAreActive(): bool
    {
        return $this->currentFilterState() !== self::FILTER_DEFAULTS;
    }

    private function validatedTitle(
        string $value,
        string $field,
        string $label,
        int $max,
    ): string {
        $title = trim($value);
        $validator = Validator::make(
            ['title' => $title],
            ['title' => ['required', 'string', 'max:' . $max]],
            ['title.required' => __('Enter a :label.', ['label' => $label])],
            ['title' => $label],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                $field => $validator->errors()->first('title'),
            ]);
        }

        return $title;
    }

    private function validatedColor(mixed $value, string $field): ?string
    {
        $color = trim((string) $value);

        if ($color === '') {
            return null;
        }

        if (! TagColor::isValid($color)) {
            throw ValidationException::withMessages([
                $field => __('Use a hex color like #ff6699.'),
            ]);
        }

        return TagColor::normalize($color);
    }

    private function nextGroupTagOrder(int $groupId): int
    {
        return (int) DB::table('genre_group_genre')
            ->where('genre_group_id', $groupId)
            ->max('order') + 1;
    }

    private function validEditingGroupIds(): array
    {
        $groupIds = $this->normalizedEditingGroupIds();

        if ($groupIds->isEmpty()) {
            return [];
        }

        return GenreGroup::query()
            ->ordered()
            ->whereKey($groupIds)
            ->get(['id'])
            ->modelKeys();
    }

    private function validEditingRelationshipIds(array $genreIds): array
    {
        $genreIds = $this->normalizedEditingRelationshipIds($genreIds);

        if ($genreIds->isEmpty()) {
            return [];
        }

        return Genre::query()
            ->whereKey($genreIds)
            ->orderBy('id')
            ->get(['id'])
            ->modelKeys();
    }

    /**
     * @return Collection<int, int>
     */
    private function normalizedEditingRelationshipIds(array $genreIds): Collection
    {
        return collect($genreIds)
            ->map(fn($genreId): string => (string) $genreId)
            ->filter(fn(string $genreId): bool => ctype_digit($genreId))
            ->map(fn(string $genreId): int => (int) $genreId)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function normalizedEditingGroupIds(): Collection
    {
        return collect($this->editingTagGroupIds)
            ->map(fn($groupId): string => (string) $groupId)
            ->filter(fn(string $groupId): bool => ctype_digit($groupId))
            ->map(fn(string $groupId): int => (int) $groupId)
            ->unique()
            ->values();
    }

    private function orderedGroups(): Collection
    {
        return GenreGroup::query()
            ->ordered()
            ->get();
    }

    private function orderedTagsInGroup(int $groupId): Collection
    {
        $group = GenreGroup::query()->find($groupId);

        if (! $group) {
            return collect();
        }

        return $group->genres()->get();
    }

    private function moveOrderedIds(Collection $ids, int $modelId, int $direction): ?array
    {
        $ids = array_values($ids->map(fn($id): int => (int) $id)->all());
        $index = array_search($modelId, $ids, true);

        if ($index === false) {
            return null;
        }

        $target = $index + ($direction < 0 ? -1 : 1);

        if (! array_key_exists($target, $ids)) {
            return null;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        return $ids;
    }

    private function reorderIds(Collection $ids, int $modelId, int $position): ?array
    {
        $ids = array_values($ids->map(fn($id): int => (int) $id)->all());
        $index = array_search($modelId, $ids, true);

        if ($index === false) {
            return null;
        }

        $position = max(0, min($position, count($ids) - 1));
        $moved = array_splice($ids, $index, 1);
        array_splice($ids, $position, 0, $moved);

        return $ids;
    }

    private function saveGroupOrder(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $index => $id) {
                GenreGroup::query()
                    ->whereKey($id)
                    ->where('order', '!=', $index + 1)
                    ->update(['order' => $index + 1]);
            }
        });

        $this->setNotice('Order saved.');
    }

    private function saveGroupTagOrder(int $groupId, array $genreIds): void
    {
        DB::transaction(function () use ($groupId, $genreIds): void {
            foreach (array_values($genreIds) as $index => $genreId) {
                DB::table('genre_group_genre')
                    ->where('genre_group_id', $groupId)
                    ->where('genre_id', $genreId)
                    ->where('order', '!=', $index + 1)
                    ->update([
                        'order' => $index + 1,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->setNotice('Order saved.');
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function groupTagItemIds(string $item): array
    {
        [$groupId, $genreId] = array_pad(explode('|', $item, 2), 2, null);

        if (! is_string($groupId) || ! is_string($genreId) || ! ctype_digit($groupId) || ! ctype_digit($genreId)) {
            return [null, null];
        }

        return [(int) $groupId, (int) $genreId];
    }

    private function setNotice(string $notice): void
    {
        $this->notice = $notice;
        $this->noticeId++;
    }

    private function clearNotice(): void
    {
        $this->notice = '';
    }
}
