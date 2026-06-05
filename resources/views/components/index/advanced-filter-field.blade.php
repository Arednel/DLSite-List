@props(['field', 'filterOptions' => []])

@switch($field['field'])
    @case('title')
        <div class="filter-widget title">
            <label class="widget-header" for="filter_title">Title</label>
            <input id="filter_title" type="text" name="title" wire:model="draft.title" placeholder="Japanese or English title">
        </div>
    @break

    @case('notes')
        <div class="filter-widget notes">
            <label class="widget-header" for="filter_notes">Notes</label>
            <input id="filter_notes" type="text" name="notes" wire:model="draft.notes" placeholder="Notes text">
        </div>
    @break

    @case('score')
        <x-index.filter-select id="filter_score" name="score" label="Score" :options="$filterOptions['scores']" placeholder="Any score"
            wire:model="draft.score" />
    @break

    @case('series')
        <div class="filter-widget series">
            <label class="widget-header" for="filter_series">Series</label>
            <input id="filter_series" type="text" name="series" wire:model="draft.series" placeholder="Series name"
                data-autocomplete-source="series" data-autocomplete-mode="single"
                data-autocomplete-url="{{ route('autocomplete.series', [], false) }}">
        </div>
    @break

    @case('age_category')
        <x-index.filter-select id="filter_age_category" name="age_category" label="Age" :options="$filterOptions['age_categories']"
            placeholder="All Works" wire:model="draft.age_category" />
    @break

    @case('progress')
        <x-index.filter-select id="filter_progress" name="progress" label="Progress" :options="$filterOptions['progress']"
            placeholder="Any progress" wire:model="draft.progress" />
    @break

    @case('circle')
        <div class="filter-widget circle">
            <label class="widget-header" for="filter_circle">Circle</label>
            <input id="filter_circle" type="text" name="circle" wire:model="draft.circle" placeholder="Circle or maker ID">
        </div>
    @break

    @case('scenario')
    @case('illustration')

    @case('voice_actor')
    @case('author')
        <div class="filter-widget {{ $field['class'] }}">
            <label class="widget-header" for="filter_{{ $field['field'] }}">{{ $field['label'] }}</label>
            <input id="filter_{{ $field['field'] }}" type="text" name="{{ $field['field'] }}"
                wire:model="draft.{{ $field['field'] }}" placeholder="{{ $field['label'] }}">
        </div>
    @break

    @case('description')
        <div class="filter-widget description">
            <label class="widget-header" for="filter_description">Description</label>
            <input id="filter_description" type="text" name="description" wire:model="draft.description"
                placeholder="Description text">
        </div>
    @break

    @case('tags')
        <div class="filter-widget tags">
            <label class="widget-header" for="filter_tags">Tags</label>
            <div class="filter-field-stack">
                <textarea id="filter_tags" name="tags" rows="3" wire:model="draft.tags"
                    placeholder='Comma-separated. Use double quotes for tags that contain commas, e.g. "Junior / Senior (at work, school, etc)", Office Lady'
                    data-autocomplete-source="tags" data-autocomplete-mode="csv"
                    data-autocomplete-url="{{ route('autocomplete.tags', [], false) }}"></textarea>
                <x-index.segmented-radio-group name="tag_match" :options="$filterOptions['tag_match'] ?? []" wire:model="draft.tag_match" />
            </div>
        </div>
    @break

    @case('priority')
        <x-index.filter-select id="filter_priority" name="priority" label="Priority" :options="$filterOptions['priorities']"
            placeholder="Any priority" wire:model="draft.priority" />
    @break

    @case('num_re_listen_times')
        <div class="filter-widget num-re-listen-times">
            <label class="widget-header" for="filter_num_re_listen_times">Total Times Re-listened</label>
            <input id="filter_num_re_listen_times" type="number" min="0" name="num_re_listen_times"
                wire:model="draft.num_re_listen_times" placeholder="Exact value">
        </div>
    @break

    @case('re_listen_value')
        <x-index.filter-select id="filter_re_listen_value" name="re_listen_value" label="Re-listen Value" :options="$filterOptions['re_listen_values']"
            placeholder="Any value" wire:model="draft.re_listen_value" />
    @break

    @case('start_date')
        <div class="filter-widget start-date">
            <span class="widget-header">Start Date</span>
            <div class="filter-field-stack filter-date-range">
                <label class="filter-date-control" for="filter_start_date_from">
                    <span>From</span>
                    <input id="filter_start_date_from" type="date" name="start_date_from"
                        wire:model="draft.start_date_from" aria-label="Start Date from">
                </label>
                <label class="filter-date-control" for="filter_start_date_to">
                    <span>To</span>
                    <input id="filter_start_date_to" type="date" name="start_date_to" wire:model="draft.start_date_to"
                        aria-label="Start Date to">
                </label>
            </div>
        </div>
    @break

    @case('end_date')
        <div class="filter-widget end-date">
            <span class="widget-header">Finish Date</span>
            <div class="filter-field-stack filter-date-range">
                <label class="filter-date-control" for="filter_end_date_from">
                    <span>From</span>
                    <input id="filter_end_date_from" type="date" name="end_date_from" wire:model="draft.end_date_from"
                        aria-label="Finish Date from">
                </label>
                <label class="filter-date-control" for="filter_end_date_to">
                    <span>To</span>
                    <input id="filter_end_date_to" type="date" name="end_date_to" wire:model="draft.end_date_to"
                        aria-label="Finish Date to">
                </label>
            </div>
        </div>
    @break

    @case('created_at')
        <div class="filter-widget created-at">
            <span class="widget-header">Added to the site Date</span>
            <div class="filter-field-stack filter-date-range">
                <label class="filter-date-control" for="filter_created_at_from">
                    <span>From</span>
                    <input id="filter_created_at_from" type="date" name="created_at_from"
                        wire:model="draft.created_at_from" aria-label="Added Date from">
                </label>
                <label class="filter-date-control" for="filter_created_at_to">
                    <span>To</span>
                    <input id="filter_created_at_to" type="date" name="created_at_to" wire:model="draft.created_at_to"
                        aria-label="Added Date to">
                </label>
            </div>
        </div>
    @break

    @case('updated_at')
        <div class="filter-widget updated-at">
            <span class="widget-header">Updated Date</span>
            <div class="filter-field-stack filter-date-range">
                <label class="filter-date-control" for="filter_updated_at_from">
                    <span>From</span>
                    <input id="filter_updated_at_from" type="date" name="updated_at_from"
                        wire:model="draft.updated_at_from" aria-label="Updated Date from">
                </label>
                <label class="filter-date-control" for="filter_updated_at_to">
                    <span>To</span>
                    <input id="filter_updated_at_to" type="date" name="updated_at_to" wire:model="draft.updated_at_to"
                        aria-label="Updated Date to">
                </label>
            </div>
        </div>
    @break
@endswitch
