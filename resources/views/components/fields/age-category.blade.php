@props([
    'options' => [],
    'value' => '',
])

<tr>
    <td class="form-table-cell">Age Category</td>
    <td class="form-table-cell">
        <select id="age_category" name="age_category" class="form-control" required>
            <option value="" @selected(old('age_category', $value) === '')>Select age category</option>
            @foreach ($options as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected(old('age_category', $value) === $optionValue)>
                    {{ $label }}</option>
            @endforeach
        </select>
        @if ($errors->has('age_category'))
            <div class="text-error">{{ $errors->first('age_category') }}</div>
        @endif
    </td>
</tr>
