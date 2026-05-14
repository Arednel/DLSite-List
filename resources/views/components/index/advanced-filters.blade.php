@props([
    'filterOptions' => [],
    'filterActive' => false,
    'hasCurrentTagFilter' => false,
])

<div x-data="indexAdvancedFilters()">
    <button type="button" class="advanced-options-button {{ $filterActive ? 'is-active' : '' }}"
        data-index-filter-open aria-controls="advanced-options-modal"
        x-bind:aria-expanded="filtersOpen.toString()"
        x-on:click="openFilters()">
        <i class="fa-solid fa-sliders"></i>
        Filter
    </button>

    <div id="advanced-options-modal" class="advanced-options-modal" data-index-filter-modal
        x-cloak
        x-show="filtersOpen"
        x-bind:aria-hidden="(!filtersOpen).toString()"
        x-on:keydown.escape.window="closeFilters()">
        <button type="button" class="advanced-options-backdrop" data-index-filter-close aria-label="Close filters"
            x-on:click="closeFilters()"></button>
        <div class="advanced-options-panel" data-index-filter-panel role="dialog" aria-modal="true"
            aria-labelledby="advanced-options-title">
            <button type="button" class="advanced-options-close" data-index-filter-close aria-label="Close filters"
                x-on:click="closeFilters()">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <form wire:submit.prevent="applyFilters" x-on:submit="closeFilters()">
                <h2 id="advanced-options-title" class="advanced-options-header">
                    Filter <span class="description">Apply one or more filters to the current list.</span>
                </h2>

                <div class="filter-widget title">
                    <label class="widget-header" for="filter_title">Title</label>
                    <input id="filter_title" type="text" name="title" wire:model="draft.title"
                        placeholder="Japanese or English title">
                </div>

                <div class="filter-widget series">
                    <label class="widget-header" for="filter_series">Series</label>
                    <input id="filter_series" type="text" name="series" wire:model="draft.series"
                        placeholder="Series name">
                </div>

                <div class="filter-widget notes">
                    <label class="widget-header" for="filter_notes">Notes</label>
                    <input id="filter_notes" type="text" name="notes" wire:model="draft.notes"
                        placeholder="Notes text">
                </div>

                <x-index.filter-select id="filter_age_category" name="age_category" label="Age" :options="$filterOptions['age_categories']"
                    placeholder="All Works" wire:model="draft.age_category" />

                <x-index.filter-select id="filter_progress" name="progress" label="Progress" :options="$filterOptions['progress']"
                    placeholder="Any progress" wire:model="draft.progress" />

                <x-index.filter-select id="filter_score" name="score" label="Score" :options="$filterOptions['scores']"
                    placeholder="Any score" wire:model="draft.score" />

                <x-index.filter-select id="filter_priority" name="priority" label="Priority" :options="$filterOptions['priorities']"
                    placeholder="Any priority" wire:model="draft.priority" />

                <div class="filter-widget">
                    <label class="widget-header" for="filter_num_re_listen_times">Total Times Re-listened</label>
                    <input id="filter_num_re_listen_times" type="number" min="0" name="num_re_listen_times"
                        wire:model="draft.num_re_listen_times" placeholder="Exact value">
                </div>

                <x-index.filter-select id="filter_re_listen_value" name="re_listen_value" label="Re-listen Value"
                    :options="$filterOptions['re_listen_values']" placeholder="Any value" wire:model="draft.re_listen_value" />

                <div class="filter-widget tags">
                    <label class="widget-header" for="filter_tags">Tags</label>
                    <div class="filter-field-stack">
                        <textarea id="filter_tags" name="tags" rows="3" wire:model="draft.tags"
                            placeholder='Comma-separated. Use double quotes for tags that contain commas, e.g. "Junior / Senior (at work, school, etc)", Office Lady'></textarea>
                        <x-index.segmented-radio-group name="tag_match" :options="$filterOptions['tag_match'] ?? []" wire:model="draft.tag_match" />
                    </div>
                </div>

                @if ($hasCurrentTagFilter)
                    <div class="filter-widget current-tag">
                        <span class="widget-header">Tag</span>
                        <span class="filter-static-value">Current tag filter stays applied.</span>
                    </div>
                @endif

                <h2 class="advanced-options-header sort-heading">
                    Sort <span class="description">Choose one or two columns to be sorted in ascending or descending
                        order.</span>
                </h2>

                <x-index.filter-select id="sort_first_field" name="sort_first_field" label="Primary"
                    group-class="sort-widget first" :options="$filterOptions['sort_fields']" placeholder="None"
                    wire:model="draft.sort_first_field" x-ref="primarySortSelect"
                    x-on:change="setPrimarySort($event.target.value)">
                    <x-index.sort-direction-group name="sort_first_direction" :options="$filterOptions['sort_directions']" wire:model="draft.sort_first_direction"
                        x-bind:disabled="primarySort === ''" />
                </x-index.filter-select>

                <x-index.filter-select id="sort_second_field" name="sort_second_field" label="Secondary"
                    group-class="sort-widget second" :options="$filterOptions['sort_fields']" placeholder="None"
                    wire:model="draft.sort_second_field" x-ref="secondarySortSelect"
                    x-on:change="setSecondarySort($event.target.value)"
                    x-bind:disabled="primarySort === ''">
                    <x-index.sort-direction-group name="sort_second_direction" :options="$filterOptions['sort_directions']" wire:model="draft.sort_second_direction"
                        x-bind:disabled="primarySort === '' || secondarySort === ''" />
                </x-index.filter-select>

                <div class="advanced-options-actions">
                    <button type="button" class="btn-clear" wire:click="clearFilters"
                        x-on:click="closeFilters()">Clear</button>
                    <button type="submit" class="btn-apply">Apply</button>
                </div>
            </form>
        </div>
    </div>
</div>
