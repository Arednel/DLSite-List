@props([
    'value' => '',
    'required' => false,
])

<tr>
    <td class="borderClass" valign="top">Title Japanese</td>
    <td class="borderClass">
        <textarea id="work_name" name="work_name" class="inputtext" rows="3" cols="65"
            @if ($required) required @endif>{{ old('work_name', $value) }}</textarea>
        @if ($errors->has('work_name'))
            <div class="text-error">
                {{ $errors->first('work_name') }}</div>
        @endif
    </td>
</tr>
