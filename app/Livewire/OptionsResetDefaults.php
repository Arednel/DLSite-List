<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\View\View;
use Livewire\Component;

class OptionsResetDefaults extends Component
{
    use ConfirmsOptionReset;

    public function render(): View
    {
        return view('livewire.options-reset-defaults');
    }

    public function resetConfirmDelaySeconds(): int
    {
        return 3;
    }

    public function resetAll(): void
    {
        Option::resetVisibleSettingsToDefault();
        $this->completeResetWithNotice('All Options settings reset to defaults.');
        $this->dispatch('options-defaults-reset');
    }
}
