<?php

namespace App\Livewire;

use App\Models\Option;
use App\Models\Product;
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
        $hasAnyProducts = $products->isNotEmpty()
            || (trim($this->search) !== '' && Product::query()->exists());

        return view('livewire.options-work-search', [
            'products' => $products,
            'hiddenSelectedProductIds' => $this->hiddenSelectedProductIds($products),
            'hasAnyProducts' => $hasAnyProducts,
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

        $metadataLanguage = Option::effectiveDlsiteMetadataLanguage();
        $query = Product::query()
            ->select(['id', 'work_name as japanese_title']);

        $query->withLocalizedTitle($metadataLanguage->value);

        if ($search !== '') {
            $query->where(
                fn(Builder $query) => $query
                    ->whereLike('id', "%{$search}%")
                    ->orWhere(fn(Builder $query) => $query->filterTitle($search, $metadataLanguage->value))
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
