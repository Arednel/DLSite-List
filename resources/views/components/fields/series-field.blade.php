@props(['value' => ''])

<tr>
    <td class="form-table-cell" valign="top">Series</td>
    <td class="form-table-cell">
        <textarea id="series" name="series" class="form-control" rows="2" cols="65">{{ old('series', $value) }}</textarea>
    </td>
</tr>
