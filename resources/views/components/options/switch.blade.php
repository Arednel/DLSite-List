@props([
    'disabled' => false,
    'help' => null,
    'sortIgnore' => false,
    'wrapperClass' => '',
])

<label @class(['option-switch', $wrapperClass]) @if ($sortIgnore) wire:sort:ignore @endif>
    <input {{ $attributes->class(['option-switch-input'])->merge(['type' => 'checkbox', 'role' => 'switch']) }}
        @disabled($disabled)>
    <span class="option-switch-track" aria-hidden="true">
        <span class="option-switch-thumb"></span>
    </span>
    <span class="option-switch-text">{{ $slot }}</span>
    @if ($help)
        <i class="fa-solid fa-circle-question" title="{{ $help }}"></i>
    @endif
</label>
