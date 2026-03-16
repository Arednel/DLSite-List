@props([
    'name',
    'options' => [],
    'selected' => '',
    'wrapperClass' => 'tag-match-toggle',
    'optionClass' => 'tag-match-option',
])

<div class="{{ $wrapperClass }}">
    @foreach ($options as $optionValue => $label)
        <label class="{{ $optionClass }}">
            <input type="radio" name="{{ $name }}" value="{{ $optionValue }}"
                @checked((string) $selected === (string) $optionValue)>
            <span>{{ $label }}</span>
        </label>
    @endforeach
</div>
