<?php

namespace App\Livewire;

use App\Models\Option;
use Illuminate\View\View;
use Livewire\Component;

class FetchedTagEditingSettings extends Component
{
    public bool $enabled = false;

    public bool $saved = false;

    public function mount(): void
    {
        $this->enabled = Option::canEditFetchedTags();
    }

    public function render(): View
    {
        return view('livewire.fetched-tag-editing-settings');
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
        ]);

        Option::setCanEditFetchedTags($this->enabled);
        $this->enabled = Option::canEditFetchedTags();
        $this->saved = true;
    }

    public function updated(string $property): void
    {
        if ($property !== 'enabled') {
            return;
        }

        $this->saved = false;
        $this->resetValidation();
    }
}
