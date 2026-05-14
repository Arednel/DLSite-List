<?php

namespace App\Livewire;

use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class IndexPaginationSettings extends Component
{
    public string $mode = '100';

    public string $customValue = '';

    public bool $saved = false;

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
        $this->saved = true;
    }

    public function updatedMode(): void
    {
        $this->saved = false;
        $this->resetValidation();
    }

    public function updatedCustomValue(): void
    {
        $this->saved = false;
        $this->resetValidation();
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
