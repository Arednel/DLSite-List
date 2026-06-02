<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class IndexTableWidthSettings extends Component
{
    use ConfirmsOptionReset;

    public string $mode = Option::INDEX_TABLE_WIDTH_DEFAULT;

    public string $custom = '';

    public function mount(): void
    {
        $this->fillFromSetting();
    }

    public function render(): View
    {
        return view('livewire.index-table-width-settings', [
            'widthOptions' => Option::INDEX_TABLE_WIDTH_OPTIONS,
            'customMode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'mode' => ['required', Rule::in(array_keys(Option::INDEX_TABLE_WIDTH_OPTIONS))],
            'custom' => [
                'required_if:mode,' . Option::INDEX_TABLE_WIDTH_CUSTOM,
                'nullable',
                'regex:/^\d+(\.\d+)?(px|rem|em|%|vw)$/',
            ],
        ]);

        Option::setIndexTableWidth([
            'mode' => $this->mode,
            'custom' => $this->custom,
        ]);
        $this->fillFromSetting();
        $this->markSaved('Index table width setting saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetIndexTableWidthToDefault();
        $this->fillFromSetting();
        $this->completeResetWithNotice('Index table width reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->fillFromSetting();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['mode', 'custom'], true)) {
            return;
        }

        $this->clearSavedNotice();
    }

    private function fillFromSetting(): void
    {
        $width = Option::indexTableWidth();
        $this->mode = $width['mode'];
        $this->custom = $width['custom'];
    }
}
