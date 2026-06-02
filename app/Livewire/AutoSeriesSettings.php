<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class AutoSeriesSettings extends Component
{
    use ConfirmsOptionReset;

    public bool $enabled = true;

    public function mount(): void
    {
        $this->enabled = Option::autoSeriesFromTitleName();
    }

    public function render(): View
    {
        return view('livewire.auto-series-settings');
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
        ]);

        Option::setAutoSeriesFromTitleName($this->enabled);
        $this->enabled = Option::autoSeriesFromTitleName();
        $this->markSaved('Auto-series setting saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetAutoSeriesFromTitleNameToDefault();
        $this->enabled = Option::autoSeriesFromTitleName();
        $this->completeResetWithNotice('Auto-series setting reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->enabled = Option::autoSeriesFromTitleName();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if ($property !== 'enabled') {
            return;
        }

        $this->clearSavedNotice();
    }
}
