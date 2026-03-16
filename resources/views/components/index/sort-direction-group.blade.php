@props([
    'name',
    'options' => [],
    'selected' => 'asc',
    'scope' => null,
])

<div class="sort-direction-group">
    @foreach ($options as $optionValue => $label)
        <label class="sort-direction-option">
            <input type="radio" name="{{ $name }}" value="{{ $optionValue }}"
                @if ($scope !== null) data-sort-direction="{{ $scope }}" @endif
                @checked((string) $selected === (string) $optionValue)>
            <span>{{ $label }}</span>
        </label>
    @endforeach
</div>
