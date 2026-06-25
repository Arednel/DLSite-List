<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class IndexSearchSettings extends Component
{
    use ConfirmsOptionReset;

    public bool $searchHiddenDescriptions = false;

    public function mount(): void
    {
        $this->searchHiddenDescriptions = Option::indexSearchHiddenDescriptionsEnabled();
    }

    public function render(): View
    {
        return view('livewire.index-search-settings');
    }

    public function save(): void
    {
        $this->validate([
            'searchHiddenDescriptions' => ['boolean'],
        ]);

        Option::setIndexSearchHiddenDescriptionsEnabled($this->searchHiddenDescriptions);
        $this->searchHiddenDescriptions = Option::indexSearchHiddenDescriptionsEnabled();
        $this->markSaved('Index search setting saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetIndexSearchHiddenDescriptionsEnabledToDefault();
        $this->searchHiddenDescriptions = Option::indexSearchHiddenDescriptionsEnabled();
        $this->completeResetWithNotice('Index search setting reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->searchHiddenDescriptions = Option::indexSearchHiddenDescriptionsEnabled();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if ($property !== 'searchHiddenDescriptions') {
            return;
        }

        $this->clearSavedNotice();
    }
}
