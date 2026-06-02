@props([
    'filterOptions' => [],
    'filterActive' => false,
    'hasCurrentTagFilter' => false,
    'filterFields' => [],
])

<div x-data="indexAdvancedFilters()">
    <button type="button" class="advanced-options-button {{ $filterActive ? 'is-active' : '' }}" data-index-filter-open
        aria-controls="advanced-options-modal" x-bind:aria-expanded="filtersOpen.toString()" x-on:click="openFilters()">
        <i class="fa-solid fa-sliders"></i>
        Filter
    </button>

    <div id="advanced-options-modal" class="advanced-options-modal" data-index-filter-modal x-cloak x-show="filtersOpen"
        x-bind:aria-hidden="(!filtersOpen).toString()" x-on:keydown.escape.window="closeFilters()">
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

                @foreach ($filterFields as $field)
                    <x-index.advanced-filter-field :field="$field" :filter-options="$filterOptions" />
                @endforeach

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
                    <x-index.sort-direction-group name="sort_first_direction" :options="$filterOptions['sort_directions']"
                        wire:model="draft.sort_first_direction" x-bind:disabled="primarySort === ''" />
                </x-index.filter-select>

                <x-index.filter-select id="sort_second_field" name="sort_second_field" label="Secondary"
                    group-class="sort-widget second" :options="$filterOptions['sort_fields']" placeholder="None"
                    wire:model="draft.sort_second_field" x-ref="secondarySortSelect"
                    x-on:change="setSecondarySort($event.target.value)" x-bind:disabled="primarySort === ''">
                    <x-index.sort-direction-group name="sort_second_direction" :options="$filterOptions['sort_directions']"
                        wire:model="draft.sort_second_direction"
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
