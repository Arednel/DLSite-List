<?php

namespace App\Livewire;

use App\Enums\AutocompleteOrder;
use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class AutocompleteSettings extends Component
{
    public string $tagOrder = '';

    public string $seriesOrder = '';

    public bool $saved = false;

    public function mount(): void
    {
        $this->fillFromSettings();
    }

    public function render(): View
    {
        return view('livewire.autocomplete-settings', [
            'orderOptions' => AutocompleteOrder::options(),
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'tagOrder' => ['required', Rule::enum(AutocompleteOrder::class)],
            'seriesOrder' => ['required', Rule::enum(AutocompleteOrder::class)],
        ]);

        Option::setTagAutocompleteOrder($this->tagOrder);
        Option::setSeriesAutocompleteOrder($this->seriesOrder);
        $this->fillFromSettings();
        $this->saved = true;
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['tagOrder', 'seriesOrder'], true)) {
            return;
        }

        $this->saved = false;
        $this->resetValidation();
    }

    private function fillFromSettings(): void
    {
        $this->tagOrder = Option::tagAutocompleteOrder()->value;
        $this->seriesOrder = Option::seriesAutocompleteOrder()->value;
    }
}
