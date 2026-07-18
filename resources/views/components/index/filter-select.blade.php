@props(['id', 'name', 'label', 'options' => [], 'placeholder' => null, 'groupClass' => 'filter-widget'])

<div class="{{ $groupClass }}">
    <label class="widget-header" for="{{ $id }}">{{ __($label) }}</label>
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes }}>
        @if ($placeholder !== null)
            <option value="">{{ __($placeholder) }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}">
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
    {{ $slot }}
</div>
