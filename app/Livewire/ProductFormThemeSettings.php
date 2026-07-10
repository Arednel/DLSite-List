<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ProductFormThemeSettings extends Component
{
    use ConfirmsOptionReset;

    public string $theme = Option::PRODUCT_FORM_THEME_BLACK;

    public function mount(): void
    {
        $this->theme = Option::productFormTheme();
    }

    public function render(): View
    {
        return view('livewire.product-form-theme-settings', [
            'themeOptions' => Option::productFormThemeOptions(),
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'theme' => ['required', Rule::in(array_keys(Option::PRODUCT_FORM_THEME_OPTIONS))],
        ]);

        Option::setProductFormTheme($this->theme);
        $this->theme = Option::productFormTheme();
        $this->markSaved('Form page theme setting saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetProductFormThemeToDefault();
        $this->theme = Option::productFormTheme();
        $this->completeResetWithNotice('Form page theme reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->theme = Option::productFormTheme();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if ($property !== 'theme') {
            return;
        }

        $this->clearSavedNotice();
    }
}
