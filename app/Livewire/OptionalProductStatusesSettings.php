<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class OptionalProductStatusesSettings extends Component
{
    use ConfirmsOptionReset;

    public bool $onHoldEnabled = false;

    public bool $droppedEnabled = false;

    public function mount(): void
    {
        $this->syncFromSettings();
    }

    public function render(): View
    {
        return view('livewire.optional-product-statuses-settings');
    }

    public function save(): void
    {
        $this->validate([
            'onHoldEnabled' => ['boolean'],
            'droppedEnabled' => ['boolean'],
        ]);

        Option::setOptionalProductStatuses([
            'on_hold' => $this->onHoldEnabled,
            'dropped' => $this->droppedEnabled,
        ]);

        $this->markSaved('Optional status settings saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetOptionalProductStatusesToDefault();
        $this->syncFromSettings();
        $this->completeResetWithNotice('Optional status settings reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->syncFromSettings();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['onHoldEnabled', 'droppedEnabled'], true)) {
            return;
        }

        $this->clearSavedNotice();
    }

    private function syncFromSettings(): void
    {
        $statuses = Option::optionalProductStatuses();

        $this->onHoldEnabled = $statuses['on_hold'];
        $this->droppedEnabled = $statuses['dropped'];
    }
}
