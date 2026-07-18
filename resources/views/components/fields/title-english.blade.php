@props(['value' => ''])

<tr>
    <td class="form-table-cell" valign="top">{{ __('Title English') }}</td>
    <td class="form-table-cell">
        <textarea id="work_name_english" name="work_name_english" class="form-control form-field-long" rows="3" cols="65">{{ old('work_name_english', $value) }}</textarea>
    </td>
    <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
</tr>
