@props([
    'field',
    'product',
    'ageCategoryOptions',
    'contributorInputs',
    'englishGenres',
    'customGenres',
    'genreFetchedEnglishInput',
    'genreCustomInput',
    'readonlyFieldValues' => [],
    'monthLabels' => [],
    'days' => [],
    'years' => [],
    'showReadonlyGenreColors' => false,
])

@switch($field['field'])
    @case('title')
        @if ($field['editable'])
            <x-fields.title-japanese :value="$product->work_name" required />
            <x-fields.title-english :value="$product->work_name_english" />
        @else
            <x-fields.readonly-text label="Title Japanese" :value="$product->work_name" />
            <x-fields.readonly-text label="Title English" :value="$product->work_name_english" />
        @endif
    @break

    @case('score')
        @if ($field['editable'])
            <x-fields.score-select :value="$product->score" />
        @else
            <x-fields.readonly-text label="Score" :value="$product->score" :long="false" />
        @endif
    @break

    @case('series')
        @if ($field['editable'])
            <x-fields.series-field :value="$product->series" />
        @else
            <x-fields.readonly-text label="Series" :value="$product->series" />
        @endif
    @break

    @case('age_category')
        @if ($field['editable'])
            <x-fields.age-category :options="$ageCategoryOptions" :value="$product->age_category" />
        @else
            <x-fields.readonly-text label="Age" :value="$product->age_category === 'ALL_AGES' ? 'All Ages' : $product->age_category" :long="false" />
        @endif
    @break

    @case('progress')
        @if ($field['editable'])
            <x-fields.status-select :value="$product->progress" />
        @else
            <x-fields.readonly-text label="Progress" :value="$product->progress" :long="false" />
        @endif
    @break

    @case('circle')
        @if ($field['editable'])
            <tr>
                <td width="130" class="form-table-cell">Circle</td>
                <td class="form-table-cell">
                    <input id="circle" name="circle" class="form-control form-field-long"
                        value="{{ old('circle', $product->circle) }}" placeholder="Circle name">
                    <input id="maker_id" name="maker_id" class="form-control margin-top-8"
                        value="{{ old('maker_id', $product->maker_id) }}" placeholder="Maker ID">
                </td>
                <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
            </tr>
        @else
            <x-fields.readonly-text label="Circle" :value="$product->circle
                ? trim($product->circle . ' ' . ($product->maker_id ? '(' . $product->maker_id . ')' : ''))
                : null" />
        @endif
    @break

    @case('scenario')
    @case('illustration')

    @case('voice_actor')
    @case('author')
        @if ($field['editable'])
            <tr>
                <td width="130" class="form-table-cell">{{ $field['label'] }}</td>
                <td class="form-table-cell">
                    <textarea id="{{ $field['contributor_role'] }}" name="{{ $field['contributor_role'] }}"
                        class="form-control form-field-long" rows="2" cols="65">{{ old($field['contributor_role'], $contributorInputs[$field['contributor_role']] ?? '') }}</textarea>
                </td>
                <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
            </tr>
        @else
            <x-fields.readonly-text :label="$field['label']" :value="$contributorInputs[$field['contributor_role']] ?? null" />
        @endif
    @break

    @case('description')
        @if ($field['editable'])
            <tr>
                <td width="130" class="form-table-cell">Description</td>
                <td class="form-table-cell">
                    <textarea id="description" name="description" class="form-control form-field-long" rows="4" cols="65"
                        placeholder="Japanese description">{{ old('description', $product->description) }}</textarea>
                    <textarea id="description_english" name="description_english" class="form-control form-field-long margin-top-8"
                        rows="4" cols="65" placeholder="English description">{{ old('description_english', $product->description_english) }}</textarea>
                </td>
                <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
            </tr>
        @else
            <x-fields.readonly-text label="Description" :value="$readonlyFieldValues['description'] ?? null" rows="5" />
        @endif
    @break

    @case('tags')
        @if (!$field['editable'] && !$field['fetched_editable'])
            <x-fields.genre-readonly label="Tags" :genres="$englishGenres->merge($customGenres)" :show-color-chips="$showReadonlyGenreColors" />
        @else
            @if ($field['fetched_editable'])
                <x-fields.genre-fetched-editable :value="$genreFetchedEnglishInput" />
            @else
                <x-fields.genre-readonly label="Fetched EN Genres" :genres="$englishGenres" :show-color-chips="$showReadonlyGenreColors" />
            @endif

            @if ($field['editable'])
                <x-fields.genre-custom :value="$genreCustomInput" />
            @else
                <x-fields.genre-readonly label="Custom Tags" :genres="$customGenres" empty="No custom tags." :show-color-chips="$showReadonlyGenreColors" />
            @endif
        @endif
    @break

    @case('notes')
        @if ($field['editable'])
            <x-fields.notes :value="$product->notes" />
        @else
            <x-fields.readonly-text label="Notes" :value="$readonlyFieldValues['notes'] ?? null" rows="5" />
        @endif
    @break

    @case('start_date')
        @if ($field['editable'])
            <x-fields.start-date :month-labels="$monthLabels" :days="$days" :years="$years" :month-value="data_get($product->start_date, 'month')" :day-value="data_get($product->start_date, 'day')"
                :year-value="data_get($product->start_date, 'year')" />
        @else
            <x-fields.readonly-text label="Start Date" :value="$readonlyFieldValues['start_date'] ?? null" :long="false" />
        @endif
    @break

    @case('end_date')
        @if ($field['editable'])
            <x-fields.finish-date :month-labels="$monthLabels" :days="$days" :years="$years" :month-value="data_get($product->end_date, 'month')" :day-value="data_get($product->end_date, 'day')"
                :year-value="data_get($product->end_date, 'year')" />
        @else
            <x-fields.readonly-text label="Finish Date" :value="$readonlyFieldValues['end_date'] ?? null" :long="false" />
        @endif
    @break

    @case('num_re_listen_times')
        @if ($field['editable'])
            <x-fields.num-re-listen-times :value="$product->num_re_listen_times" />
        @else
            <x-fields.readonly-text label="Total Times Re-listened" :value="$readonlyFieldValues['num_re_listen_times'] ?? null" :long="false" />
        @endif
    @break

    @case('re_listen_value')
        @if ($field['editable'])
            <x-fields.re-listen-value :value="$product->re_listen_value" />
        @else
            <x-fields.readonly-text label="Re-listen Value" :value="$readonlyFieldValues['re_listen_value'] ?? null" :long="false" />
        @endif
    @break

    @case('priority')
        @if ($field['editable'])
            <x-fields.priority :value="$product->priority" />
        @else
            <x-fields.readonly-text label="Priority" :value="$readonlyFieldValues['priority'] ?? null" :long="false" />
        @endif
    @break

@endswitch
