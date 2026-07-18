@props(['value' => ''])

<tr>
    <td class="form-table-cell" valign="top">{{ __('Series') }}</td>
    <td class="form-table-cell">
        <textarea id="series" name="series" class="form-control form-field-long" rows="2" cols="65"
            data-autocomplete-source="series" data-autocomplete-mode="single"
            data-autocomplete-url="{{ route('autocomplete.series', [], false) }}">{{ old('series', $value) }}</textarea>
    </td>
    <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
</tr>
