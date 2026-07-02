@props([
    'value' => '',
    'required' => false,
])

<tr>
    <td class="form-table-cell" valign="top">Title Japanese</td>
    <td class="form-table-cell">
        <textarea id="work_name" name="work_name" class="form-control form-field-long" rows="3" cols="65"
            @if ($required) required @endif>{{ old('work_name', $value) }}</textarea>
        @if ($errors->has('work_name'))
            <div class="text-error">
                {{ $errors->first('work_name') }}</div>
        @endif
    </td>
    <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
</tr>
