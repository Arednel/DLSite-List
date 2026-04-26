<form method="POST" action="{{ route('options.refetch-tags.start') }}" class="stack">
    @csrf
    <input type="hidden" name="scope" value="selected">

    @foreach ($hiddenSelectedProductIds as $productId)
        <input type="hidden" name="product_ids[]" value="{{ $productId }}">
    @endforeach

    <div>
        <label class="field-label" for="work-search">Select works</label>
        <input id="work-search" class="option-search" type="search" placeholder="Search by RJ ID or title..."
            wire:model.live.debounce.250ms="search">
    </div>

    <div class="work-checklist">
        @forelse ($products as $product)
            <label class="work-checklist__item" wire:key="refetch-work-{{ $product->id }}">
                <input type="checkbox" name="product_ids[]" value="{{ $product->id }}"
                    wire:model.live="selectedProductIds">
                <span>
                    <strong>{{ $product->id }}</strong>
                    {{ $product->work_name }}
                    @if ($product->work_name_english)
                        <span class="work-checklist__muted">{{ $product->work_name_english }}</span>
                    @endif
                </span>
            </label>
        @empty
            <p class="empty-state">
                {{ trim($search) === '' ? 'No works available for tag refetch.' : 'No works match this search.' }}
            </p>
        @endforelse
    </div>

    @if ($hasAnyProducts)
        <div class="option-actions">
            <button type="submit" class="tag tag--soft tag--lg is-clickable">
                Refetch selected works
            </button>
        </div>
    @endif
</form>
