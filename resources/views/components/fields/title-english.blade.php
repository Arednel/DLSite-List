@props(['value' => ''])

<tr>
    <td class="form-table-cell" valign="top">Title English</td>
    <td class="form-table-cell">
        <textarea id="work_name_english" name="work_name_english" class="form-control" rows="3" cols="65">{{ old('work_name_english', $value) }}</textarea>
    </td>
</tr>
