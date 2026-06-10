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

    public function mount(): void
    {
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
    }

    public function render(): View
    {
        return view('livewire.tag-library-display-settings');
    }

    public function save(): void
    {
        $this->validate([
            'expandedByDefault' => ['boolean'],
        ]);

        Option::setTagLibraryTagsExpandedByDefault($this->expandedByDefault);
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->markSaved('Tag Library display setting saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetTagLibraryTagsExpandedByDefaultToDefault();
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->completeResetWithNotice('Tag Library display setting reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if ($property !== 'expandedByDefault') {
            return;
        }

        $this->clearSavedNotice();
    }
}
