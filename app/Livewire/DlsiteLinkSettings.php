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

    public bool $announceEnabled = true;

    public function mount(): void
    {
        $this->enabled = Option::dlsiteAgeAppropriateLinksEnabled();
        $this->announceEnabled = Option::dlsiteAnnounceLinksEnabled();
    }

    public function render(): View
    {
        return view('livewire.dlsite-link-settings');
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
            'announceEnabled' => ['boolean'],
        ]);

        Option::setDlsiteAgeAppropriateLinksEnabled($this->enabled);
        Option::setDlsiteAnnounceLinksEnabled($this->announceEnabled);
        $this->enabled = Option::dlsiteAgeAppropriateLinksEnabled();
        $this->announceEnabled = Option::dlsiteAnnounceLinksEnabled();
        $this->markSaved('DLSite link settings saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetDlsiteAgeAppropriateLinksEnabledToDefault();
        Option::resetDlsiteAnnounceLinksEnabledToDefault();
        $this->enabled = Option::dlsiteAgeAppropriateLinksEnabled();
        $this->announceEnabled = Option::dlsiteAnnounceLinksEnabled();
        $this->completeResetWithNotice('DLSite link settings reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->enabled = Option::dlsiteAgeAppropriateLinksEnabled();
        $this->announceEnabled = Option::dlsiteAnnounceLinksEnabled();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['enabled', 'announceEnabled'], true)) {
            return;
        }

        $this->clearSavedNotice();
    }
}
