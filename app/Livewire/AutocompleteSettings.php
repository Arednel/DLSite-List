<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Enums\AutocompleteOrder;
use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class AutocompleteSettings extends Component
{
    use ConfirmsOptionReset;

    public string $tagOrder = '';

    public string $seriesOrder = '';

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
        $this->markSaved('Autocomplete settings saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetAutocompleteToDefault();
        $this->fillFromSettings();
        $this->completeResetWithNotice('Autocomplete settings reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->fillFromSettings();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['tagOrder', 'seriesOrder'], true)) {
            return;
        }

        $this->clearSavedNotice();
    }

    private function fillFromSettings(): void
    {
        $this->tagOrder = Option::tagAutocompleteOrder()->value;
        $this->seriesOrder = Option::seriesAutocompleteOrder()->value;
    }
}
