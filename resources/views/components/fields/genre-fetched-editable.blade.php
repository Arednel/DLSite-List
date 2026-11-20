@props(['value' => ''])

<tr>
    <td width="130" class="form-table-cell">{{ __('Fetched Tags') }}</td>
    <td class="form-table-cell">
        <textarea id="genre_fetched" name="genre_fetched" class="form-textarea form-field-long"
            placeholder="{{ __('Comma-separated. Use double quotes for tags that contain commas.') }}" rows="3"
            cols="65" data-autocomplete-source="tags" data-autocomplete-mode="csv"
            data-autocomplete-url="{{ route('autocomplete.tags', [], false) }}">{{ $value }}</textarea>
    </td>
    <td class="form-table-cell form-table-cell--help-icon">
        <i class="fa-solid fa-circle-question"
            title='{{ __('Comma-separated. Use double quotes for tags that contain commas, e.g. "Junior / Senior (at work, school, etc)", Office Lady') }}'></i>
    </td>
</tr>
