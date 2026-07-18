@props(['name', 'options'])

<div class="tag-match-toggle">
    @foreach ($options as $optionValue => $label)
        <label class="tag-match-option">
            <input type="radio" name="{{ $name }}" value="{{ $optionValue }}" {{ $attributes }}>
            <span>{{ $label }}</span>
        </label>
    @endforeach
</div>
