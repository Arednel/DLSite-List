@props(['id', 'name', 'label', 'options' => [], 'placeholder' => null, 'groupClass' => 'filter-widget'])

<div class="{{ $groupClass }}">
    <label class="widget-header" for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}">
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
    {{ $slot }}
</div>
