@props([
    'value' => 'Plan to Listen',
    'options' => ['Plan to Listen', 'Listening', 'Completed'],
])

<tr>
    <td class="borderClass">Status</td>
    <td class="borderClass">
        <select id="progress" name="progress" class="inputtext">
            @foreach ($options as $option)
                <option value="{{ $option }}" @selected(old('progress', $value) === $option)>
                    {{ $option }}</option>
            @endforeach
        </select>
    </td>
</tr>
