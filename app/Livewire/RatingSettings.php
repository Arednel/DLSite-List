<?php

namespace App\Livewire;

use App\Enums\RatingScale;
use App\Enums\RatingVisualStyle;
use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class RatingSettings extends Component
{
    use ConfirmsOptionReset;

    public string $scale = RatingScale::Ten->value;

    public string $visualStyle = RatingVisualStyle::Simple->value;

    public function mount(): void
    {
        $this->fillFromSettings();
    }

    public function render(): View
    {
        return view('livewire.rating-settings', [
            'scaleOptions' => RatingScale::options(),
            'visualStyleOptions' => RatingVisualStyle::options(),
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'scale' => ['required', Rule::enum(RatingScale::class)],
            'visualStyle' => ['required', Rule::enum(RatingVisualStyle::class)],
        ]);

        Option::setRatingScale($this->scale);
        Option::setRatingVisualStyle($this->visualStyle);
        $this->fillFromSettings();
        $this->markSaved('Rating settings saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetRatingSettingsToDefault();
        $this->fillFromSettings();
        $this->completeResetWithNotice('Rating settings reset to default.');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['scale', 'visualStyle'], true)) {
            $this->clearSavedNotice();
        }
    }

    private function fillFromSettings(): void
    {
        $this->scale = Option::ratingScale()->value;
        $this->visualStyle = Option::ratingVisualStyle()->value;
    }
}
