<section class="tag-library-panel" aria-labelledby="tag-library-heading">
    <div class="tag-library-panel-heading">
        <h1 id="tag-library-heading" class="tag-library-section-title">{{ __('Tags') }}</h1>
    </div>

    <div class="tag-library-toolbar">
        <label class="tag-library-search">
            <span>{{ __('Search tags') }}</span>
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search tags...') }}">
        </label>

        <form wire:submit.prevent="createTag" class="tag-library-create-form">
            <label for="new-tag-title">{{ __('Add tag') }}</label>
            <div class="tag-library-create-row">
                <input id="new-tag-title" type="text" wire:model="newTagTitle"
                    placeholder="{{ __('New tag title') }}" data-autocomplete-source="tags"
                    data-autocomplete-mode="single" data-autocomplete-url="{{ route('autocomplete.tags', [], false) }}">
                <button type="submit" class="tag-library-action">{{ __('Add') }}</button>
            </div>
            @error('newTagTitle')
                <div class="tag-library-message tag-library-message--error">{{ $message }}</div>
            @enderror
        </form>

    </div>

    @if ($notice !== '')
        <div class="tag-library-toast" wire:key="tag-library-notice-{{ $noticeId }}" role="status">
            {{ __($notice) }}
        </div>
    @endif

    <div class="tag-library-panel-heading tag-library-panel-heading--all-tags">
        <h2 class="tag-library-section-title">{{ __('All Tags') }}</h2>
        <label class="tag-library-switch tag-library-switch--toolbar">
            <input type="checkbox" class="tag-library-switch-input" wire:model.live="tagEditMode" role="switch">
            <span class="tag-library-switch-track" aria-hidden="true">
                <span class="tag-library-switch-thumb"></span>
            </span>
            <span class="tag-library-switch-text">{{ __('Edit tags') }}</span>
        </label>
        <button type="button" class="tag-library-toggle" wire:click="toggleAllTags"
            aria-expanded="{{ $showAllTags ? 'true' : 'false' }}">
            <span class="tag-library-toggle-icon">{{ $showAllTags ? 'v' : '>' }}</span>
            <span>{{ $showAllTags ? __('Hide tags list') : __('Show tags list') }}</span>
        </button>
    </div>

    <div class="tag-library-filters">
        <label>
            <span>{{ __('Index visibility') }}</span>
            <select wire:model.live="visibilityFilter">
                <option value="all">{{ __('All') }}</option>
                <option value="visible">{{ __('Visible on Index') }}</option>
                <option value="hidden_tag">{{ __('Hidden by tag') }}</option>
                <option value="hidden_group">{{ __('Hidden by group') }}</option>
                <option value="hidden_any">{{ __('Hidden by tag or group') }}</option>
            </select>
        </label>

        <label>
            <span>{{ __('Group status') }}</span>
            <select wire:model.live="groupStatusFilter">
                <option value="all">{{ __('All') }}</option>
                <option value="grouped">{{ __('Grouped') }}</option>
                <option value="ungrouped">{{ __('Ungrouped') }}</option>
            </select>
        </label>

        <label>
            <span>{{ __('Group') }}</span>
            <select wire:model.live="groupFilter">
                <option value="all">{{ __('Any group') }}</option>
                @foreach ($groupOptions as $groupOption)
                    <option value="{{ $groupOption['id'] }}">{{ $groupOption['title'] }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span>{{ __('Usage') }}</span>
            <select wire:model.live="usageFilter">
                <option value="all">{{ __('All') }}</option>
                <option value="empty">{{ __('Empty') }}</option>
                <option value="used">{{ __('Used') }}</option>
            </select>
        </label>
    </div>

    @if ($showAllTags)
        <div class="tag-library-tags">
            @forelse ($genres as $genre)
                <span @class([
                    'tag-library-tag-shell',
                    'tag-library-tag-shell--deletable' => $genre->pivot_count === 0,
                ]) wire:key="tag-library-tag-{{ $genre->id }}">
                    @if ($tagEditMode)
                        <button type="button" @class([
                            'tag-library-tag',
                            'tag-library-tag--editable',
                            'tag-library-tag--background-colored' => $genre->has_background_color,
                            'tag-library-tag--text-colored' => $genre->has_font_color,
                        ])
                            @if (filled($genre->color_style)) style="{{ $genre->color_style }}" @endif
                            wire:click="openTagSettings({{ $genre->id }})"
                            aria-label="{{ __('Edit tag settings for :tag', ['tag' => $genre->title]) }}">
                            <span class="tag-library-tag-title">{{ $genre->title }}</span>
                            @if ($genre->hidden_on_index_effective)
                                <span class="tag-library-tag-status" aria-label="{{ __('Hidden tag') }}"
                                    title="{{ __('Hidden tag') }}">
                                    <span class="tag-library-status-dot tag-library-status-dot--hidden-tag"
                                        aria-hidden="true"></span>
                                    <span class="tag-library-sr-only">{{ __('Tag hidden on Index') }}</span>
                                </span>
                            @endif
                            <span @class([
                                'tag-library-tag-count',
                                'tag-library-tag-count--empty' => $genre->products_count === 0,
                            ])>{{ $genre->products_count }}</span>
                        </button>
                    @else
                        <a @class([
                            'tag-library-tag',
                            'tag-library-tag--background-colored' => $genre->has_background_color,
                            'tag-library-tag--text-colored' => $genre->has_font_color,
                        ])
                            @if (filled($genre->color_style)) style="{{ $genre->color_style }}" @endif
                            href="{{ route('index', ['age_category' => '', 'progress' => '', 'genre' => $genre->id]) }}">
                            <span class="tag-library-tag-title">{{ $genre->title }}</span>
                            @if ($genre->hidden_on_index_effective)
                                <span class="tag-library-tag-status" aria-label="{{ __('Hidden tag') }}"
                                    title="{{ __('Hidden tag') }}">
                                    <span class="tag-library-status-dot tag-library-status-dot--hidden-tag"
                                        aria-hidden="true"></span>
                                    <span class="tag-library-sr-only">{{ __('Tag hidden on Index') }}</span>
                                </span>
                            @endif
                            <span @class([
                                'tag-library-tag-count',
                                'tag-library-tag-count--empty' => $genre->products_count === 0,
                            ])>{{ $genre->products_count }}</span>
                        </a>
                    @endif

                    @if ($genre->pivot_count === 0)
                        <button type="button" class="tag-library-delete-button"
                            aria-label="{{ __('Delete empty tag :tag', ['tag' => $genre->title]) }}"
                            wire:click="askDeleteTag({{ $genre->id }})">
                            x
                        </button>
                    @endif
                </span>
            @empty
                <p class="tag-library-empty">
                    {{ __('No :language, custom, or empty tags found.', ['language' => $fetchedLanguageCode]) }}</p>
            @endforelse
        </div>
    @else
        <p class="tag-library-collapsed">
            {{ __('All tags are collapsed. Use search or press "Show all tags" button to show them.') }}</p>
    @endif

    <section class="tag-library-groups" aria-labelledby="tag-library-groups-heading">
        <div class="tag-library-subheading">
            <h2 id="tag-library-groups-heading">{{ __('Tag Groups') }}</h2>
            <label class="tag-library-switch">
                <input type="checkbox" class="tag-library-switch-input" wire:model.live="indexGroupOrderingEnabled"
                    role="switch">
                <span class="tag-library-switch-track" aria-hidden="true">
                    <span class="tag-library-switch-thumb"></span>
                </span>
                <span class="tag-library-switch-text">{{ __('Enable group ordering on Index') }}</span>
            </label>
            <form wire:submit.prevent="createGroup" class="tag-library-create-form tag-library-group-create-form">
                <label for="new-group-title">{{ __('Add group') }}</label>
                <div class="tag-library-create-row">
                    <input id="new-group-title" type="text" wire:model="newGroupTitle"
                        placeholder="{{ __('New group title') }}">
                    <button type="submit" class="tag-library-action">{{ __('Add') }}</button>
                </div>
                @error('newGroupTitle')
                    <div class="tag-library-message tag-library-message--error">{{ $message }}</div>
                @enderror
            </form>
        </div>

        <div class="tag-library-group-list" wire:sort="reorderGroups">
            @forelse ($groups as $groupIndex => $group)
                <article class="tag-library-group-card" wire:key="tag-group-{{ $group->id }}"
                    wire:sort:item="{{ $group->id }}">
                    <div class="tag-library-group-header">
                        <button type="button" class="tag-library-drag-handle" wire:sort:handle
                            aria-label="{{ __('Drag group :group', ['group' => $group->title]) }}">
                            <i class="fa-solid fa-arrows-up-down" aria-hidden="true"></i>
                        </button>
                        <div class="tag-library-order-buttons" wire:sort:ignore>
                            <button type="button" wire:click.stop="moveGroup({{ $group->id }}, -1)"
                                @disabled($groupIndex === 0)>{{ __('Up') }}</button>
                            <button type="button" wire:click.stop="moveGroup({{ $group->id }}, 1)"
                                @disabled($groupIndex === $groups->count() - 1)>{{ __('Down') }}</button>
                        </div>

                        <form wire:submit.prevent="renameGroup({{ $group->id }})"
                            class="tag-library-group-title-form" wire:sort:ignore>
                            <input type="text" wire:model="groupTitles.{{ $group->id }}"
                                aria-label="{{ __('Group title :group', ['group' => $group->title]) }}">
                            <button type="submit" class="tag-library-action">{{ __('Rename') }}</button>
                        </form>

                        <label class="tag-library-check tag-library-switch" wire:sort:ignore>
                            <input type="checkbox" class="tag-library-switch-input"
                                wire:model.live="groupHidden.{{ $group->id }}"
                                wire:change="saveGroupHidden({{ $group->id }})" role="switch">
                            <span class="tag-library-switch-track" aria-hidden="true">
                                <span class="tag-library-switch-thumb"></span>
                            </span>
                            <span class="tag-library-switch-text">{{ __('Hide group on Index') }}</span>
                        </label>

                        <button type="button" class="tag-library-danger-button" wire:sort:ignore
                            wire:click="askDeleteGroup({{ $group->id }})">
                            {{ __('Delete group') }}
                        </button>
                    </div>

                    @error("groupTitles.{$group->id}")
                        <div class="tag-library-message tag-library-message--error" wire:sort:ignore>{{ $message }}
                        </div>
                    @enderror

                    <div class="tag-library-color-control-set" wire:sort:ignore>
                        <div class="tag-library-color-controls">
                            <label class="tag-library-color-picker">
                                <span>{{ __('Group background color') }}</span>
                                <input type="color" wire:model.live="groupColors.{{ $group->id }}"
                                    wire:change="saveGroupColor({{ $group->id }})"
                                    aria-label="{{ __('Color picker for group :group', ['group' => $group->title]) }}">
                            </label>
                            <label class="tag-library-color-hex">
                                <span>{{ __('Hex') }}</span>
                                <input type="text" wire:model.blur="groupColors.{{ $group->id }}"
                                    wire:change="saveGroupColor({{ $group->id }})" placeholder="#000000"
                                    maxlength="7"
                                    aria-label="{{ __('Hex color for group :group', ['group' => $group->title]) }}">
                            </label>
                            <button type="button" class="tag-library-action tag-library-color-clear"
                                wire:click="clearGroupColor({{ $group->id }})">
                                {{ __('Clear color') }}
                            </button>
                        </div>
                        <div class="tag-library-color-controls">
                            <label class="tag-library-color-picker">
                                <span>{{ __('Group font color') }}</span>
                                <input type="color" wire:model.live="groupTextColors.{{ $group->id }}"
                                    wire:change="saveGroupColor({{ $group->id }})"
                                    aria-label="{{ __('Font color picker for group :group', ['group' => $group->title]) }}">
                            </label>
                            <label class="tag-library-color-hex">
                                <span>{{ __('Hex') }}</span>
                                <input type="text" wire:model.blur="groupTextColors.{{ $group->id }}"
                                    wire:change="saveGroupColor({{ $group->id }})" placeholder="#000000"
                                    maxlength="7"
                                    aria-label="{{ __('Hex font color for group :group', ['group' => $group->title]) }}">
                            </label>
                            <button type="button" class="tag-library-action tag-library-color-clear"
                                wire:click="clearGroupTextColor({{ $group->id }})">
                                {{ __('Clear font color') }}
                            </button>
                        </div>
                    </div>
                    @error("groupColors.{$group->id}")
                        <div class="tag-library-message tag-library-message--error" wire:sort:ignore>{{ $message }}
                        </div>
                    @enderror
                    @error("groupTextColors.{$group->id}")
                        <div class="tag-library-message tag-library-message--error" wire:sort:ignore>{{ $message }}
                        </div>
                    @enderror

                    <form wire:submit.prevent="addTagToGroup({{ $group->id }})" class="tag-library-group-add"
                        wire:sort:ignore>
                        <input type="text" wire:model="groupTagInputs.{{ $group->id }}"
                            placeholder="{{ __('Add tag to :group', ['group' => $group->title]) }}"
                            data-autocomplete-source="tags" data-autocomplete-mode="single"
                            data-autocomplete-url="{{ route('autocomplete.tags', [], false) }}">
                        <button type="submit" class="tag-library-action">{{ __('Add tag') }}</button>
                    </form>
                    @error("groupTagInputs.{$group->id}")
                        <div class="tag-library-message tag-library-message--error" wire:sort:ignore>{{ $message }}
                        </div>
                    @enderror

                    <div class="tag-library-group-tags" wire:sort="reorderGroupTags">
                        @forelse ($group->genres as $tagIndex => $genre)
                            <div class="tag-library-group-tag-row"
                                wire:key="tag-group-{{ $group->id }}-tag-{{ $genre->id }}"
                                wire:sort:item="{{ $group->id }}|{{ $genre->id }}">
                                <button type="button" class="tag-library-drag-handle" wire:sort:handle
                                    aria-label="{{ __('Drag tag :tag', ['tag' => $genre->title]) }}">
                                    <i class="fa-solid fa-arrows-up-down" aria-hidden="true"></i>
                                </button>
                                <div class="tag-library-order-buttons" wire:sort:ignore>
                                    <button type="button"
                                        wire:click.stop="moveGroupTag({{ $group->id }}, {{ $genre->id }}, -1)"
                                        @disabled($tagIndex === 0)>{{ __('Up') }}</button>
                                    <button type="button"
                                        wire:click.stop="moveGroupTag({{ $group->id }}, {{ $genre->id }}, 1)"
                                        @disabled($tagIndex === $group->genres->count() - 1)>{{ __('Down') }}</button>
                                </div>

                                <span @class([
                                    'tag-library-group-tag-title',
                                    'tag-library-group-tag-title--background-colored' =>
                                        $genre->has_background_color,
                                    'tag-library-group-tag-title--text-colored' => $genre->has_font_color,
                                ])
                                    @if (filled($genre->color_style)) style="{{ $genre->color_style }}" @endif>{{ $genre->title }}</span>

                                <label class="tag-library-check tag-library-switch" wire:sort:ignore>
                                    <input type="checkbox" class="tag-library-switch-input"
                                        wire:model.live="tagHidden.{{ $genre->id }}"
                                        wire:change="saveTagHidden({{ $genre->id }})" role="switch">
                                    <span class="tag-library-switch-track" aria-hidden="true">
                                        <span class="tag-library-switch-thumb"></span>
                                    </span>
                                    <span class="tag-library-switch-text">{{ __('Hide tag on Index') }}</span>
                                </label>

                                <button type="button" class="tag-library-remove-from-group" wire:sort:ignore
                                    aria-label="{{ __('Remove :tag from :group', ['tag' => $genre->title, 'group' => $group->title]) }}"
                                    wire:click="removeTagFromGroup({{ $group->id }}, {{ $genre->id }})">
                                    x
                                </button>
                            </div>
                        @empty
                            <p class="tag-library-empty tag-library-empty--compact" wire:sort:ignore>
                                {{ __('No tags in this group yet.') }}</p>
                        @endforelse
                    </div>
                </article>
            @empty
                <p class="tag-library-empty">{{ __('No tag groups yet.') }}</p>
            @endforelse
        </div>
    </section>

    @if ($confirmingDeleteTag)
        @teleport('body')
            <div class="tag-library-modal-backdrop" role="dialog" aria-modal="true"
                aria-labelledby="tag-delete-modal-title" wire:click.self="cancelDeleteTag"
                wire:keydown.escape.window="cancelDeleteTag">
                <div class="tag-library-modal-card">
                    <h3 id="tag-delete-modal-title">{{ __('Are you sure?') }}</h3>
                    <p>{{ __('Delete empty tag ":tag"?', ['tag' => $confirmingDeleteTag->title]) }}</p>

                    <div class="tag-library-modal-actions">
                        <button type="button" class="tag-library-modal-cancel" wire:click="cancelDeleteTag">
                            {{ __('Cancel') }}
                        </button>
                        <button type="button" class="tag-library-modal-confirm" wire:click="deleteTag">
                            {{ __('Delete tag') }}
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    @if ($editingTag)
        @teleport('body')
            <div class="tag-library-modal-backdrop" role="dialog" aria-modal="true"
                aria-labelledby="tag-settings-modal-title" wire:click.self="closeTagSettings"
                wire:keydown.escape.window="closeTagSettings">
                <form class="tag-library-modal-card tag-library-modal-card--wide" wire:submit.prevent="saveTagSettings">
                    <h3 id="tag-settings-modal-title">{{ __('Edit tag settings') }}</h3>
                    <p class="tag-library-modal-tag-title">{{ $editingTag->title }}</p>

                    <div class="tag-library-modal-section">
                        <label class="tag-library-check tag-library-switch">
                            <input type="checkbox" class="tag-library-switch-input" wire:model="editingTagHidden"
                                role="switch">
                            <span class="tag-library-switch-track" aria-hidden="true">
                                <span class="tag-library-switch-thumb"></span>
                            </span>
                            <span class="tag-library-switch-text">{{ __('Hide tag on Index') }}</span>
                        </label>
                    </div>

                    <fieldset class="tag-library-modal-fieldset">
                        <legend>{{ __('Tag color') }}</legend>
                        <div class="tag-library-color-control-set">
                            <div class="tag-library-color-controls tag-library-color-controls--modal">
                                <label class="tag-library-color-picker">
                                    <span>{{ __('Background color') }}</span>
                                    <input type="color" wire:model.live="editingTagColor"
                                        aria-label="{{ __('Color picker for :tag', ['tag' => $editingTag->title]) }}">
                                </label>
                                <label class="tag-library-color-hex">
                                    <span>{{ __('Hex') }}</span>
                                    <input type="text" wire:model.blur="editingTagColor" placeholder="#000000"
                                        maxlength="7"
                                        aria-label="{{ __('Hex color for :tag', ['tag' => $editingTag->title]) }}">
                                </label>
                                <button type="button" class="tag-library-action tag-library-color-clear"
                                    wire:click="clearEditingTagColor">
                                    {{ __('Clear color') }}
                                </button>
                            </div>
                            <div class="tag-library-color-controls tag-library-color-controls--modal">
                                <label class="tag-library-color-picker">
                                    <span>{{ __('Font color') }}</span>
                                    <input type="color" wire:model.live="editingTagTextColor"
                                        aria-label="{{ __('Font color picker for :tag', ['tag' => $editingTag->title]) }}">
                                </label>
                                <label class="tag-library-color-hex">
                                    <span>{{ __('Hex') }}</span>
                                    <input type="text" wire:model.blur="editingTagTextColor" placeholder="#000000"
                                        maxlength="7"
                                        aria-label="{{ __('Hex font color for :tag', ['tag' => $editingTag->title]) }}">
                                </label>
                                <button type="button" class="tag-library-action tag-library-color-clear"
                                    wire:click="clearEditingTagTextColor">
                                    {{ __('Clear font color') }}
                                </button>
                            </div>
                        </div>
                        @error('editingTagColor')
                            <div class="tag-library-message tag-library-message--error">{{ $message }}</div>
                        @enderror
                        @error('editingTagTextColor')
                            <div class="tag-library-message tag-library-message--error">{{ $message }}</div>
                        @enderror
                    </fieldset>

                    <fieldset class="tag-library-modal-fieldset">
                        <legend>{{ __('Groups') }}</legend>

                        @if ($groupOptions->isEmpty())
                            <p class="tag-library-empty tag-library-empty--compact">{{ __('No tag groups yet.') }}</p>
                        @else
                            <div class="tag-library-modal-group-search-wrap">
                                <label class="tag-library-modal-group-search">
                                    <span>{{ __('Search tag groups') }}</span>
                                    <input type="search" wire:model.live.debounce.250ms="editingTagGroupSearch"
                                        placeholder="{{ __('Search tag groups...') }}">
                                </label>

                                @if (trim($editingTagGroupSearch) !== '')
                                    <div class="tag-library-modal-group-dropdown">
                                        @forelse ($editingAvailableGroupOptions as $availableGroupOption)
                                            <button type="button" class="tag-library-modal-group-result"
                                                wire:click="addEditingTagGroup({{ $availableGroupOption['id'] }})">
                                                {{ $availableGroupOption['title'] }}
                                            </button>
                                        @empty
                                            <p class="tag-library-empty tag-library-empty--compact">
                                                {{ __('No matching groups.') }}</p>
                                        @endforelse
                                    </div>
                                @endif
                            </div>

                            <div class="tag-library-modal-group-plaques" aria-label="{{ __('Assigned tag groups') }}">
                                @forelse ($editingSelectedGroupOptions as $selectedGroupOption)
                                    <span class="tag-library-modal-group-plaque">
                                        <span>{{ $selectedGroupOption['title'] }}</span>
                                        <button type="button"
                                            aria-label="{{ __('Remove :group from this tag', ['group' => $selectedGroupOption['title']]) }}"
                                            wire:click="removeEditingTagGroup({{ $selectedGroupOption['id'] }})">
                                            x
                                        </button>
                                    </span>
                                @empty
                                    <p class="tag-library-empty tag-library-empty--compact">
                                        {{ __('No groups assigned.') }}</p>
                                @endforelse
                            </div>
                        @endif
                    </fieldset>

                    <div class="tag-library-modal-actions">
                        <button type="button" class="tag-library-modal-cancel" wire:click="closeTagSettings">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="tag-library-modal-confirm">
                            {{ __('Save tag settings') }}
                        </button>
                    </div>
                </form>
            </div>
        @endteleport
    @endif

    @if ($confirmingDeleteGroup)
        @teleport('body')
            <div class="tag-library-modal-backdrop" role="dialog" aria-modal="true"
                aria-labelledby="group-delete-modal-title" wire:click.self="cancelDeleteGroup"
                wire:keydown.escape.window="cancelDeleteGroup">
                <div class="tag-library-modal-card">
                    <h3 id="group-delete-modal-title">{{ __('Are you sure?') }}</h3>
                    <p>{{ __('Delete group ":group"? Tags in this group will stay in the Tag Library.', ['group' => $confirmingDeleteGroup->title]) }}
                    </p>

                    <div class="tag-library-modal-actions">
                        <button type="button" class="tag-library-modal-cancel" wire:click="cancelDeleteGroup">
                            {{ __('Cancel') }}
                        </button>
                        <button type="button" class="tag-library-modal-confirm" wire:click="deleteGroup">
                            {{ __('Delete group') }}
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</section>
