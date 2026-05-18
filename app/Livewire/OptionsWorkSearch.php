<?php

namespace App\Livewire;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class OptionsWorkSearch extends Component
{
    public string $search = '';

    /**
     * @var list<string>
     */
    public array $selectedProductIds = [];

    public function render(): View
    {
        $products = $this->products();

        return view('livewire.options-work-search', [
            'products' => $products,
            'hiddenSelectedProductIds' => $this->hiddenSelectedProductIds($products),
            'hasAnyProducts' => $products->isNotEmpty() || Product::query()->exists(),
        ]);
    }

    public function updatedSelectedProductIds(): void
    {
        $this->selectedProductIds = collect($this->selectedProductIds)
            ->map(fn(mixed $productId): string => (string) $productId)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function products(): Collection
    {
        $query = Product::query();

        $search = trim($this->search);

        if ($search !== '') {
            $query->whereAny(['id', 'work_name', 'work_name_english'], 'like', "%{$search}%");
        }

        return $query
            ->orderByNumericRj()
            ->get(['id', 'work_name', 'work_name_english']);
    }

    /**
     * @return list<string>
     */
    private function hiddenSelectedProductIds(Collection $visibleProducts): array
    {
        return array_values(array_diff(
            $this->selectedProductIds,
            $visibleProducts->pluck('id')->all()
        ));
    }
}
