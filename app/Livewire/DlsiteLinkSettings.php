<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DlsiteLinkSettings extends Component
{
    use ConfirmsOptionReset;

    public bool $enabled = false;

    public function mount(): void
    {
        $this->enabled = Option::dlsiteAgeAppropriateLinksEnabled();
    }

    public function render(): View
    {
        return view('livewire.dlsite-link-settings');
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
        ]);

        Option::setDlsiteAgeAppropriateLinksEnabled($this->enabled);
        $this->enabled = Option::dlsiteAgeAppropriateLinksEnabled();
        $this->markSaved('DLSite link setting saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetDlsiteAgeAppropriateLinksEnabledToDefault();
        $this->enabled = Option::dlsiteAgeAppropriateLinksEnabled();
        $this->completeResetWithNotice('DLSite link setting reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->enabled = Option::dlsiteAgeAppropriateLinksEnabled();
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
