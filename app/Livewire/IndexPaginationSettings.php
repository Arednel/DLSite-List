<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class IndexPaginationSettings extends Component
{
    use ConfirmsOptionReset;

    public string $mode = '100';

    public string $customValue = '';

    public function mount(): void
    {
        $this->fillFromSetting();
    }

    public function render(): View
    {
        return view('livewire.index-pagination-settings', [
            'fixedOptions' => Option::fixedIndexPerPageOptions(),
            'unlimitedValue' => Option::INDEX_PER_PAGE_UNLIMITED,
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'mode' => [
                'required',
                Rule::in([
                    ...array_map('strval', Option::FIXED_INDEX_PER_PAGE_OPTIONS),
                    'custom',
                    Option::INDEX_PER_PAGE_UNLIMITED,
                ]),
            ],
            'customValue' => ['required_if:mode,custom', 'nullable', 'integer', 'min:1'],
        ]);

        Option::setIndexPerPage($this->resolvedValue());
        $this->fillFromSetting();
        $this->markSaved('Index pagination setting saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetIndexPerPageToDefault();
        $this->fillFromSetting();
        $this->completeResetWithNotice('Index pagination reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->fillFromSetting();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['mode', 'customValue'], true)) {
            return;
        }

        $this->clearSavedNotice();
    }

    private function fillFromSetting(): void
    {
        $value = Option::indexPerPage();

        if ($value === Option::INDEX_PER_PAGE_UNLIMITED) {
            $this->mode = Option::INDEX_PER_PAGE_UNLIMITED;
            $this->customValue = '';

            return;
        }

        if (in_array($value, Option::FIXED_INDEX_PER_PAGE_OPTIONS, true)) {
            $this->mode = (string) $value;
            $this->customValue = '';

            return;
        }

        $this->mode = 'custom';
        $this->customValue = (string) $value;
    }

    private function resolvedValue(): int|string
    {
        if ($this->mode === Option::INDEX_PER_PAGE_UNLIMITED) {
            return Option::INDEX_PER_PAGE_UNLIMITED;
        }

        if ($this->mode === 'custom') {
            return (int) $this->customValue;
        }

        return (int) $this->mode;
    }
}
