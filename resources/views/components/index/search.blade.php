<div {{ $attributes->class(['search-container']) }}>
    <form wire:submit.prevent="applySearch" class="search-form">
        <input type="text" name="search" wire:model="searchInput" placeholder="{{ __('Search...') }}"
            class="search-input">
        <button type="submit" class="search-button" aria-label="{{ __('Search') }}">
            <i class="fa-solid fa-magnifying-glass"></i>
        </button>
    </form>
</div>
