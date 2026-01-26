@props(['value' => ''])

<tr>
    <td class="borderClass" valign="top">Title English</td>
    <td class="borderClass">
        <textarea id="work_name_english" name="work_name_english" class="inputtext" rows="3" cols="65">{{ old('work_name_english', $value) }}</textarea>
    </td>
</tr>
