<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class TagLibraryDisplaySettings extends Component
{
    use ConfirmsOptionReset;

    public bool $expandedByDefault = false;

    public bool $indexGroupOrderingEnabled = false;

    public function mount(): void
    {
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->indexGroupOrderingEnabled = Option::tagLibraryIndexGroupOrderingEnabled();
    }

    public function render(): View
    {
        return view('livewire.tag-library-display-settings');
    }

    public function save(): void
    {
        $this->validate([
            'expandedByDefault' => ['boolean'],
            'indexGroupOrderingEnabled' => ['boolean'],
        ]);

        Option::setTagLibraryTagsExpandedByDefault($this->expandedByDefault);
        Option::setTagLibraryIndexGroupOrderingEnabled($this->indexGroupOrderingEnabled);
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->indexGroupOrderingEnabled = Option::tagLibraryIndexGroupOrderingEnabled();
        $this->markSaved('Tag Library settings saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetTagLibraryTagsExpandedByDefaultToDefault();
        Option::resetTagLibraryIndexGroupOrderingEnabledToDefault();
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->indexGroupOrderingEnabled = Option::tagLibraryIndexGroupOrderingEnabled();
        $this->completeResetWithNotice('Tag Library settings reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->indexGroupOrderingEnabled = Option::tagLibraryIndexGroupOrderingEnabled();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['expandedByDefault', 'indexGroupOrderingEnabled'], true)) {
            return;
        }

        $this->clearSavedNotice();
    }
}
