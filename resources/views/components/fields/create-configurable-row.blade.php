@props([
    'field',
    'isCustomCreate' => false,
    'ageCategoryOptions' => [],
    'monthLabels' => [],
    'days' => [],
    'years' => [],
])

@switch($field['field'])
    @case('rj_code')
        <x-fields.rj-input />
    @break

    @case('progress')
        <x-fields.status-select />
    @break

    @case('score')
        <x-fields.score-select />
    @break

    @case('series')
        <x-fields.series-field />
    @break

    @case('title')
        <x-fields.title-japanese :required="$isCustomCreate" />
        <x-fields.title-english />
    @break

    @case('tags')
        <x-fields.genre-custom />
    @break

    @case('notes')
        <x-fields.notes />
    @break

    @case('age_category')
        <x-fields.age-category :options="$ageCategoryOptions" :required="$isCustomCreate" />
    @break

    @case('circle')
        <tr>
            <td width="130" class="form-table-cell">Circle</td>
            <td class="form-table-cell">
                <input id="circle" name="circle" class="form-control" value="{{ old('circle') }}" placeholder="Circle name">
                <input id="maker_id" name="maker_id" class="form-control margin-top-8" value="{{ old('maker_id') }}"
                    placeholder="Maker ID">
            </td>
        </tr>
    @break

    @case('scenario')
    @case('illustration')

    @case('voice_actor')
    @case('author')
        @php($contributorValue = old($field['contributor_role'], ''))
        <tr>
            <td width="130" class="form-table-cell">{{ $field['label'] }}</td>
            <td class="form-table-cell">
                <textarea id="{{ $field['contributor_role'] }}" name="{{ $field['contributor_role'] }}" class="form-control"
                    rows="2" cols="65">{{ is_array($contributorValue) ? implode(', ', $contributorValue) : $contributorValue }}</textarea>
            </td>
        </tr>
    @break

    @case('description_japanese')
        <tr>
            <td width="130" class="form-table-cell">Japanese Description</td>
            <td class="form-table-cell">
                <textarea id="description" name="description" class="form-control" rows="4" cols="65"
                    placeholder="Japanese description">{{ old('description') }}</textarea>
            </td>
        </tr>
    @break

    @case('description_english')
        <tr>
            <td width="130" class="form-table-cell">English Description</td>
            <td class="form-table-cell">
                <textarea id="description_english" name="description_english" class="form-control" rows="4" cols="65"
                    placeholder="English description">{{ old('description_english') }}</textarea>
            </td>
        </tr>
    @break

    @case('image')
        <x-fields.work-image-upload />
    @break

    @case('sample_images')
        <x-fields.sample-images-upload />
    @break

    @case('start_date')
        <x-fields.start-date :month-labels="$monthLabels" :days="$days" :years="$years" />
    @break

    @case('end_date')
        <x-fields.finish-date :month-labels="$monthLabels" :days="$days" :years="$years" />
    @break

    @case('num_re_listen_times')
        <x-fields.num-re-listen-times />
    @break

    @case('re_listen_value')
        <x-fields.re-listen-value />
    @break

    @case('priority')
        <x-fields.priority />
    @break
@endswitch
