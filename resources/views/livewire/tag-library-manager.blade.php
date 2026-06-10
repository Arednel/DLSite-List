<section class="tag-library-panel" aria-labelledby="tag-library-heading">
    <div class="tag-library-panel-heading">
        <h1 id="tag-library-heading" class="tag-library-section-title">Tags</h1>
        <button type="button" class="tag-library-toggle" wire:click="toggleAllTags"
            aria-expanded="{{ $showAllTags ? 'true' : 'false' }}">
            <span class="tag-library-toggle-icon">{{ $showAllTags ? 'v' : '>' }}</span>
            <span>{{ $showAllTags ? 'Hide all tags' : 'Show all tags' }}</span>
        </button>
    </div>

    <div class="tag-library-toolbar">
        <label class="tag-library-search">
            <span>Search tags</span>
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search tags...">
        </label>

        <form wire:submit.prevent="createTag" class="tag-library-create-form">
            <label for="new-tag-title">Add tag</label>
            <div class="tag-library-create-row">
                <input id="new-tag-title" type="text" wire:model="newTagTitle" placeholder="New tag title">
                <button type="submit" class="tag-library-action">Add</button>
            </div>
            @error('newTagTitle')
                <div class="tag-library-message tag-library-message--error">{{ $message }}</div>
            @enderror
        </form>
    </div>

    @if ($notice !== '')
        <div class="tag-library-message">{{ $notice }}</div>
    @endif

    @if ($showAllTags)
        <div class="tag-library-tags">
            @forelse ($genres as $genre)
                <span class="tag-library-tag-shell" wire:key="tag-library-tag-{{ $genre->id }}">
                    <a class="tag-library-tag"
                        href="{{ route('index', ['age_category' => '', 'progress' => '', 'genre' => $genre->id]) }}">
                        <span class="tag-library-tag-title">{{ $genre->title }}</span>
                        <span @class([
                            'tag-library-tag-count',
                            'tag-library-tag-count--empty' => $genre->products_count === 0,
                        ])>{{ $genre->products_count }}</span>
                    </a>

                    @if ($genre->pivot_count === 0)
                        <button type="button" class="tag-library-delete-button"
                            aria-label="Delete empty tag {{ $genre->title }}"
                            wire:click="askDeleteTag({{ $genre->id }})">
                            x
                        </button>
                    @endif
                </span>
            @empty
                <p class="tag-library-empty">No English, custom, or empty tags found.</p>
            @endforelse
        </div>
    @else
        <p class="tag-library-collapsed">All tags are collapsed. Use search or press "Show all tags" button to show
            them.</p>
    @endif

    @if ($confirmingDeleteTag)
        @teleport('body')
            <div class="tag-library-modal-backdrop" role="dialog" aria-modal="true"
                aria-labelledby="tag-delete-modal-title" wire:click.self="cancelDeleteTag"
                wire:keydown.escape.window="cancelDeleteTag">
                <div class="tag-library-modal-card">
                    <h3 id="tag-delete-modal-title">Are you sure?</h3>
                    <p>Delete empty tag "{{ $confirmingDeleteTag->title }}"?</p>

                    <div class="tag-library-modal-actions">
                        <button type="button" class="tag-library-modal-cancel" wire:click="cancelDeleteTag">
                            Cancel
                        </button>
                        <button type="button" class="tag-library-modal-confirm" wire:click="deleteTag">
                            Delete tag
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</section>
