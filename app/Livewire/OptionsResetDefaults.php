<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class OptionsResetDefaults extends Component
{
    use ConfirmsOptionReset;

    private const NOTICE_SESSION_KEY = 'options_reset_notice';

    #[Locked]
    public string $activeTab = 'general';

    public function mount(string $activeTab = 'general'): void
    {
        $this->activeTab = $activeTab;
    }

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

        session()->flash(
            self::NOTICE_SESSION_KEY,
            'All Options settings reset to defaults.',
        );

        $this->redirectRoute('options.index', ['tab' => $this->activeTab]);
    }
}
