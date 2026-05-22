@props(['value' => ''])

<tr>
    <td class="form-table-cell" valign="top">Notes</td>
    <td class="form-table-cell">
        <textarea id="add_notes" name="notes" class="form-control" rows="5" cols="65">{{ old('notes', $value) }}</textarea>
    </td>
</tr>
