@props([
    'value' => '',
    'label',
])

<tr>
    <td class="form-table-cell" valign="top">{{ $label }}</td>
    <td class="form-table-cell">
        <textarea id="localized_title" name="localized_title" class="form-control form-field-long" rows="3" cols="65">{{ old('localized_title', $value) }}</textarea>
    </td>
    <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
</tr>
