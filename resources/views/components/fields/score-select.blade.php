@props([
    'value' => null,
    'options' => [
        '10' => '(10) Masterpiece',
        '9' => '(9) Great',
        '8' => '(8) Very Good',
        '7' => '(7) Good',
        '6' => '(6) Nice',
        '5' => '(5) Average',
        '4' => '(4) Below Average',
        '3' => '(3) Unremarkable',
        '2' => '(2) Subtle',
        '1' => '(1) Faint',
    ],
])

<tr>
    <td class="borderClass">Your Score</td>
    <td class="borderClass">
        <select id="score" name="score" class="inputtext">
            <option value="" @selected(old('score', $value) === null || old('score', $value) === '')>Select score</option>
            @foreach ($options as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected((string) old('score', $value) === (string) $optionValue)>
                    {{ $label }}</option>
            @endforeach
        </select>
    </td>
</tr>
