<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class IndexImageViewerSettings extends Component
{
    use ConfirmsOptionReset;

    public bool $enabled = false;

    public function mount(): void
    {
        $this->enabled = Option::indexImageViewerEnabled();
    }

    public function render(): View
    {
        return view('livewire.index-image-viewer-settings');
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
        ]);

        Option::setIndexImageViewerEnabled($this->enabled);
        $this->markSaved('Image viewer setting saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetIndexImageViewerEnabledToDefault();
        $this->enabled = false;
        $this->completeResetWithNotice('Image viewer setting reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->enabled = Option::indexImageViewerEnabled();
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
