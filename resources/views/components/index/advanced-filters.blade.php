@props([
    'filters' => new \App\Support\ProductIndexFilters(),
    'filterOptions' => [],
])

<button type="button" class="advanced-options-button {{ $filters->hasActiveFilters() ? 'is-active' : '' }}"
    data-index-filter-open aria-controls="advanced-options-modal" aria-expanded="false">
    <i class="fa-solid fa-sliders"></i>
    Filter
</button>

<div id="advanced-options-modal" class="advanced-options-modal" data-index-filter-modal aria-hidden="true" hidden>
    <button type="button" class="advanced-options-backdrop" data-index-filter-close aria-label="Close filters"></button>
    <div class="advanced-options-panel" data-index-filter-panel role="dialog" aria-modal="true"
        aria-labelledby="advanced-options-title">
        <button type="button" class="advanced-options-close" data-index-filter-close aria-label="Close filters">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <form method="GET" action="{{ route('index') }}">
            @if ($filters->search !== '')
                <input type="hidden" name="search" value="{{ $filters->search }}">
            @endif

            @if ($filters->genre !== '')
                <input type="hidden" name="genre" value="{{ $filters->genre }}">
            @endif

            <h2 id="advanced-options-title" class="advanced-options-header">
                Filter <span class="description">Apply one or more filters to the current list.</span>
            </h2>

            <div class="filter-widget title">
                <label class="widget-header" for="filter_title">Title</label>
                <input id="filter_title" type="text" name="title" value="{{ $filters->title }}"
                    placeholder="Japanese or English title">
            </div>

            <div class="filter-widget series">
                <label class="widget-header" for="filter_series">Series</label>
                <input id="filter_series" type="text" name="series" value="{{ $filters->series }}"
                    placeholder="Series name">
            </div>

            <div class="filter-widget notes">
                <label class="widget-header" for="filter_notes">Notes</label>
                <input id="filter_notes" type="text" name="notes" value="{{ $filters->notes }}"
                    placeholder="Notes text">
            </div>

            <x-index.filter-select id="filter_age_category" name="age_category" label="Age" :options="$filterOptions['age_categories']"
                :selected="$filters->ageCategory?->value ?? ''" placeholder="All Works" />

            <x-index.filter-select id="filter_progress" name="progress" label="Progress" :options="$filterOptions['progress']"
                :selected="$filters->progress?->value ?? ''" placeholder="Any progress" />

            <x-index.filter-select id="filter_score" name="score" label="Score" :options="$filterOptions['scores']" :selected="$filters->score?->value ?? ''"
                placeholder="Any score" />

            <x-index.filter-select id="filter_priority" name="priority" label="Priority" :options="$filterOptions['priorities']"
                :selected="$filters->priority?->value ?? ''" placeholder="Any priority" />

            <div class="filter-widget">
                <label class="widget-header" for="filter_num_re_listen_times">Total Times Re-listened</label>
                <input id="filter_num_re_listen_times" type="number" min="0" name="num_re_listen_times"
                    value="{{ $filters->numReListenTimes ?? '' }}" placeholder="Exact value">
            </div>

            <x-index.filter-select id="filter_re_listen_value" name="re_listen_value" label="Re-listen Value"
                :options="$filterOptions['re_listen_values']" :selected="$filters->reListenValue?->value ?? ''" placeholder="Any value" />

            <div class="filter-widget tags">
                <label class="widget-header" for="filter_tags">Tags</label>
                <div class="filter-field-stack">
                    <textarea id="filter_tags" name="tags" rows="3"
                        placeholder='Comma-separated. Use double quotes for tags that contain commas, e.g. "Junior / Senior (at work, school, etc)", Office Lady'>{{ $filters->tags }}</textarea>
                    <x-index.segmented-radio-group name="tag_match" :options="$filterOptions['tag_match'] ?? []"
                        :selected="$filters->resolvedTagMatch()->value" />
                </div>
            </div>

            @if ($filters->genre !== '')
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
                group-class="sort-widget first" :options="$filterOptions['sort_fields']" :selected="$filters->primarySort?->field->value ?? ''" placeholder="None"
                data-sort-field="primary">
                <x-index.sort-direction-group name="sort_first_direction" :options="$filterOptions['sort_directions']" :selected="$filters->primarySort?->direction->value ?? 'desc'"
                    scope="primary" />
            </x-index.filter-select>

            <x-index.filter-select id="sort_second_field" name="sort_second_field" label="Secondary"
                group-class="sort-widget second" :options="$filterOptions['sort_fields']" :selected="$filters->secondarySort?->field->value ?? ''" placeholder="None"
                data-sort-field="secondary">
                <x-index.sort-direction-group name="sort_second_direction" :options="$filterOptions['sort_directions']" :selected="$filters->secondarySort?->direction->value ?? 'desc'"
                    scope="secondary" />
            </x-index.filter-select>

            <div class="advanced-options-actions">
                <a href="{{ route('index', [], false) }}" class="btn-clear">Clear</a>
                <button type="submit" class="btn-apply">Apply</button>
            </div>
        </form>
    </div>
</div>

@once
    <script src="{{ asset('scripts/index-filters.js') }}" defer></script>
@endonce
