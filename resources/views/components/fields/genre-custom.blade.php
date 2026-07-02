@props(['value' => ''])

<tr>
    <td width="130" class="form-table-cell">Custom Tags</td>
    <td class="form-table-cell">
        <textarea id="genre_custom" name="genre_custom" class="form-textarea form-field-long"
            placeholder="Comma-separated. Use double quotes for tags that contain commas, e.g. &quot;Junior / Senior (at work, school, etc)&quot;, Office Lady"
            rows="5" cols="65" data-autocomplete-source="tags" data-autocomplete-mode="csv"
            data-autocomplete-url="{{ route('autocomplete.tags', [], false) }}">{{-- Add ", " faster tag input, if there is any tag --}}{{ old('genre_custom', filled($value) ? $value . ', ' : '') }}</textarea>
    </td>
    <td class="form-table-cell form-table-cell--help-icon">
        <i class="fa-solid fa-circle-question"
            title='Comma-separated. Use double quotes for tags that contain commas, e.g. "Junior / Senior (at work, school, etc)", Office Lady'></i>
    </td>
</tr>
