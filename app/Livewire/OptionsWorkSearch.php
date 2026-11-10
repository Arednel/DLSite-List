<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductLocalization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class OptionsWorkSearch extends Component
{
    public string $search = '';

    public bool $checkImages = false;

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
        $search = trim($this->search);

        $query = Product::query()
            ->select(['id', 'work_name as japanese_title'])
            ->withAggregate([
                'localizations as english_title' => fn(Builder $query) =>
                $query->where('locale', ProductLocalization::LOCALE_ENGLISH),
            ], 'title');

        if ($search !== '') {
            $query->where(
                fn(Builder $query) => $query
                    ->whereLike('id', "%{$search}%")
                    ->orWhere(fn(Builder $query) => $query->filterTitle($search))
            );
        }

        return $query
            ->orderByNumericCode()
            ->toBase()
            ->get();
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
