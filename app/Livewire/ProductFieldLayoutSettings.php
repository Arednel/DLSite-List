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

    private const LAYOUTS = [
        'index',
        'filter',
        'sort',
        'edit',
        'quick_add',
        'custom_quick_add',
    ];

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

    public string $savedLayout = '';

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
        foreach (self::LAYOUTS as $layout) {
            $this->persistLayout($layout);
        }

        $this->fillFromSettings();
        $this->savedLayout = 'all';
        $this->markSaved('Field layouts saved.');
    }

    public function saveLayout(string $layout): void
    {
        if (! $this->persistLayout($layout)) {
            return;
        }

        $this->fillLayoutFromSettings($layout);
        $this->savedLayout = $layout;
        $this->markSaved('Field layout saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetFieldLayoutsToDefault();
        Option::resetIndexSortFieldLayoutToDefault();
        $this->fillFromSettings();
        $this->savedLayout = 'all';
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
        $this->clearLayoutSavedNotice();
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

    public function fieldLayoutHelp(string $layout, string $field): ?string
    {
        if ($field !== ProductIndexSortField::UpdatedAt->value) {
            return null;
        }

        return match ($layout) {
            'filter' => __('Filters by when the work record was last updated in this site database.'),
            'sort' => __('Sorts by when the work record was last updated in this site database.'),
            default => null,
        };
    }

    public function updated(): void
    {
        $this->clearLayoutSavedNotice();
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->fillFromSettings();
        $this->clearLayoutSavedNotice();
    }

    private function fillFromSettings(): void
    {
        foreach (self::LAYOUTS as $layout) {
            $this->fillLayoutFromSettings($layout);
        }
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
        $this->clearLayoutSavedNotice();
    }

    private function persistLayout(string $layout): bool
    {
        switch ($layout) {
            case 'index':
                Option::setIndexFieldLayout($this->layoutFromState($this->indexOrder, $this->indexFields));
                break;
            case 'filter':
                Option::setFilterFieldLayout($this->layoutFromState($this->filterOrder, $this->filterFields));
                break;
            case 'sort':
                Option::setIndexSortFieldLayout($this->sortLayoutFromState($this->sortOrder, $this->sortFields));
                break;
            case 'edit':
                Option::setEditFieldLayout($this->layoutFromState($this->editOrder, $this->editFields));
                break;
            case 'quick_add':
                Option::setQuickAddFieldLayout(
                    $this->layoutFromState($this->quickAddOrder, $this->quickAddFields),
                );
                break;
            case 'custom_quick_add':
                Option::setCustomQuickAddFieldLayout(
                    $this->layoutFromState($this->customQuickAddOrder, $this->customQuickAddFields),
                );
                break;
            default:
                return false;
        }

        return true;
    }

    private function fillLayoutFromSettings(string $layout): void
    {
        switch ($layout) {
            case 'index':
                [$this->indexOrder, $this->indexFields] = $this->stateFromLayout(Option::indexFieldLayout());
                break;
            case 'filter':
                [$this->filterOrder, $this->filterFields] = $this->stateFromLayout(Option::filterFieldLayout());
                break;
            case 'sort':
                [$this->sortOrder, $this->sortFields] = $this->stateFromSortLayout(Option::indexSortFieldLayout());
                break;
            case 'edit':
                [$this->editOrder, $this->editFields] = $this->stateFromLayout(Option::editFieldLayout());
                break;
            case 'quick_add':
                [$this->quickAddOrder, $this->quickAddFields] = $this->stateFromLayout(Option::quickAddFieldLayout());
                break;
            case 'custom_quick_add':
                [$this->customQuickAddOrder, $this->customQuickAddFields] = $this->stateFromLayout(
                    Option::customQuickAddFieldLayout(),
                );
                break;
        }
    }

    private function clearLayoutSavedNotice(): void
    {
        $this->savedLayout = '';
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
