@props(['name', 'options' => []])

<div class="sort-direction-group">
    @foreach ($options as $optionValue => $label)
        <label class="sort-direction-option">
            <input type="radio" name="{{ $name }}" value="{{ $optionValue }}" {{ $attributes }}>
            <span>{{ $label }}</span>
        </label>
    @endforeach
</div>
