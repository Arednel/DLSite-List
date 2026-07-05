<?php

namespace App\Livewire;

use App\Enums\ProductField;
use App\Enums\ProductIndexSortField;
use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ProductFieldLayoutSettings extends Component
{
    use ConfirmsOptionReset;

    private const ORDER_PROPERTIES = [
        'indexOrder',
        'editOrder',
        'filterOrder',
        'quickAddOrder',
        'customQuickAddOrder',
        'sortOrder',
    ];

    private const FIELDS_PROPERTIES = [
        'indexFields',
        'editFields',
        'filterFields',
        'quickAddFields',
        'customQuickAddFields',
        'sortFields',
    ];

    public array $indexOrder = [];

    public array $editOrder = [];

    public array $filterOrder = [];

    public array $quickAddOrder = [];

    public array $customQuickAddOrder = [];

    public array $sortOrder = [];

    public array $indexFields = [];

    public array $editFields = [];

    public array $filterFields = [];

    public array $quickAddFields = [];

    public array $customQuickAddFields = [];

    public array $sortFields = [];

    public function mount(): void
    {
        $this->fillFromSettings();
    }

    public function render(): View
    {
        return view('livewire.product-field-layout-settings');
    }

    public function save(): void
    {
        Option::setIndexFieldLayout($this->layoutFromState($this->indexOrder, $this->indexFields));
        Option::setEditFieldLayout($this->layoutFromState($this->editOrder, $this->editFields));
        Option::setFilterFieldLayout($this->layoutFromState($this->filterOrder, $this->filterFields));
        Option::setQuickAddFieldLayout($this->layoutFromState($this->quickAddOrder, $this->quickAddFields));
        Option::setCustomQuickAddFieldLayout(
            $this->layoutFromState($this->customQuickAddOrder, $this->customQuickAddFields),
        );
        Option::setIndexSortFieldLayout($this->sortLayoutFromState($this->sortOrder, $this->sortFields));
        $this->fillFromSettings();
        $this->markSaved('Field layouts saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetFieldLayoutsToDefault();
        Option::resetIndexSortFieldLayoutToDefault();
        $this->fillFromSettings();
        $this->completeResetWithNotice('Field layouts reset to default.');
    }

    public function move(string $layout, int $index, int $direction): void
    {
        if (! in_array($layout, self::ORDER_PROPERTIES, true)) {
            return;
        }

        $target = $index + ($direction < 0 ? -1 : 1);

        if (! isset($this->{$layout}[$index], $this->{$layout}[$target])) {
            return;
        }

        [$this->{$layout}[$index], $this->{$layout}[$target]] = [$this->{$layout}[$target], $this->{$layout}[$index]];
        $this->{$layout} = array_values($this->{$layout});
        $this->clearSavedNotice();
    }

    public function layoutRows(string $orderProperty, string $fieldsProperty): array
    {
        if (
            ! in_array($orderProperty, self::ORDER_PROPERTIES, true)
            || ! in_array($fieldsProperty, self::FIELDS_PROPERTIES, true)
        ) {
            return [];
        }

        return collect($this->{$orderProperty})
            ->map(fn(string $field): mixed => $this->{$fieldsProperty}[$field] ?? null)
            ->filter(fn(mixed $row): bool => is_array($row))
            ->values()
            ->all();
    }

    public function updated(): void
    {
        $this->clearSavedNotice();
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->fillFromSettings();
        $this->clearSavedNotice();
    }

    private function fillFromSettings(): void
    {
        [$this->indexOrder, $this->indexFields] = $this->stateFromLayout(Option::indexFieldLayout());
        [$this->editOrder, $this->editFields] = $this->stateFromLayout(Option::editFieldLayout());
        [$this->filterOrder, $this->filterFields] = $this->stateFromLayout(Option::filterFieldLayout());
        [$this->quickAddOrder, $this->quickAddFields] = $this->stateFromLayout(Option::quickAddFieldLayout());
        [$this->customQuickAddOrder, $this->customQuickAddFields] = $this->stateFromLayout(
            Option::customQuickAddFieldLayout(),
        );
        [$this->sortOrder, $this->sortFields] = $this->stateFromSortLayout(Option::indexSortFieldLayout());
    }

    public function reorderLayout(string $item, int $position): void
    {
        [$layout, $field] = array_pad(explode('|', $item, 2), 2, null);

        if (
            ! is_string($field)
            || ! in_array($layout, self::ORDER_PROPERTIES, true)
        ) {
            return;
        }

        $currentIndex = array_search($field, $this->{$layout}, true);

        if ($currentIndex === false) {
            return;
        }

        $rows = array_values($this->{$layout});
        $position = max(0, min($position, count($rows) - 1));
        $movedRows = array_splice($rows, $currentIndex, 1);

        array_splice($rows, $position, 0, $movedRows);

        $this->{$layout} = array_values($rows);
        $this->clearSavedNotice();
    }

    private function stateFromLayout(array $layout): array
    {
        $order = [];
        $fields = [];

        foreach ($layout as $row) {
            if (! is_array($row)) {
                continue;
            }

            $field = ProductField::tryFrom((string) ($row['field'] ?? ''));

            if (! $field) {
                continue;
            }

            $order[] = $field->value;
            $fields[$field->value] = [
                ...$row,
                'field' => $field->value,
                'label' => (string) ($row['label'] ?? $field->label()),
            ];
        }

        return [$order, $fields];
    }

    private function layoutFromState(array $order, array $fields): array
    {
        $layout = [];

        foreach ($order as $field) {
            $field = ProductField::tryFrom((string) $field);

            if (! $field || ! isset($fields[$field->value]) || ! is_array($fields[$field->value])) {
                continue;
            }

            $layout[] = [
                ...$fields[$field->value],
                'field' => $field->value,
                'label' => (string) ($fields[$field->value]['label'] ?? $field->label()),
            ];
        }

        return $layout;
    }

    private function stateFromSortLayout(array $layout): array
    {
        $order = [];
        $fields = [];

        foreach ($layout as $row) {
            if (! is_array($row)) {
                continue;
            }

            $field = ProductIndexSortField::tryFrom((string) ($row['field'] ?? ''));

            if (! $field) {
                continue;
            }

            $order[] = $field->value;
            $fields[$field->value] = [
                'field' => $field->value,
                'label' => $field->label(),
                'visible' => (bool) ($row['visible'] ?? false),
            ];
        }

        return [$order, $fields];
    }

    private function sortLayoutFromState(array $order, array $fields): array
    {
        $layout = [];

        foreach ($order as $field) {
            $field = ProductIndexSortField::tryFrom((string) $field);

            if (! $field || ! isset($fields[$field->value]) || ! is_array($fields[$field->value])) {
                continue;
            }

            $layout[] = [
                'field' => $field->value,
                'visible' => (bool) ($fields[$field->value]['visible'] ?? false),
            ];
        }

        return $layout;
    }
}
