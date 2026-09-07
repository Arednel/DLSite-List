<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use App\Support\ProductIndexContentOverflow;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class IndexContentOverflowSettings extends Component
{
    use ConfirmsOptionReset;

    public array $overflow = [];

    public function mount(): void
    {
        $this->fillFromSetting();
    }

    public function render(): View
    {
        return view('livewire.index-content-overflow-settings', [
            'overflowTargets' => [
                'inline_notes' => 'Inline Notes',
                'notes_column' => 'Notes Column',
                'tags' => 'Tags Column',
            ],
        ]);
    }

    public function save(): void
    {
        foreach (ProductIndexContentOverflow::DEFAULTS as $target => $defaults) {
            $this->overflow[$target]['enabled'] ??= false;
            $height = $this->overflow[$target]['enabled']
                ? ($this->overflow[$target]['height'] ?? null)
                : $defaults['height'];
            $this->overflow[$target]['height'] = is_string($height)
                ? strtolower(trim($height))
                : '';
        }

        $this->validate([
            'overflow.*.enabled' => ['required', 'boolean'],
            'overflow.*.height' => [
                'required',
                'regex:' . ProductIndexContentOverflow::HEIGHT_PATTERN,
            ],
        ], [
            'overflow.*.height.regex' => __('Use a positive CSS length with one of the supported units.'),
        ]);

        Option::setIndexContentOverflow($this->overflow);

        $this->fillFromSetting();
        $this->markSaved('Overflow settings saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetIndexContentOverflowToDefault();
        $this->fillFromSetting();
        $this->completeResetWithNotice('Overflow settings reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->fillFromSetting();
        $this->clearSavedNotice();
    }

    public function updatedOverflow(): void
    {
        $this->clearSavedNotice();
    }

    private function fillFromSetting(): void
    {
        $this->overflow = Option::indexContentOverflow();
    }
}
