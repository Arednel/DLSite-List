<tr>
    <td class="form-table-cell">Your Score</td>
    <td class="form-table-cell">
        <select id="score" name="score" class="form-control">
            <option value="" @selected(old('score', $value) === null || old('score', $value) === '')>Select score</option>
            @foreach ($options as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected((string) old('score', $value) === (string) $optionValue)>
                    {{ $label }}</option>
            @endforeach
        </select>
    </td>
</tr>
