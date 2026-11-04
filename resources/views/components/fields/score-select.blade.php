<tr>
    <td class="form-table-cell">{{ __('Your Score') }}</td>
    <td class="form-table-cell">
        <select id="score" name="score" class="form-control">
            <option value="" @selected(old('score', $value) === null || old('score', $value) === '')>{{ __('Select score') }}</option>
            @foreach ($options as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected((string) old('score', $value) === (string) $optionValue)>
                    {{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td class="form-table-cell form-table-cell--help-icon">
        <i class="fa-solid fa-circle-question"
            title="{{ __('Personal scores are always entered from 1 to 10. The General rating scale changes only how the score is displayed; for example, 9 is shown as 4.5 on the 5-point scale.') }}"></i>
    </td>
</tr>
