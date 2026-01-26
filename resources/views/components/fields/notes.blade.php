@props(['value' => ''])

<tr>
    <td class="borderClass" valign="top">Notes</td>
    <td class="borderClass">
        <textarea id="add_notes" name="notes" class="inputtext" rows="5" cols="65">{{ old('notes', $value) }}</textarea>
    </td>
</tr>
