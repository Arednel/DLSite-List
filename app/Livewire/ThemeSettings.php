<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class ThemeSettings extends Component
{
    use ConfirmsOptionReset;

    public string $authenticationTheme = Option::AUTHENTICATION_PAGE_THEME_CHERRY;

    public string $formTheme = Option::PRODUCT_FORM_THEME_BLACK;

    public function mount(): void
    {
        $this->fillFromSettings();
    }

    public function render(): View
    {
        return view('livewire.theme-settings', [
            'authenticationThemeOptions' => Option::authenticationPageThemeOptions(),
            'formThemeOptions' => Option::productFormThemeOptions(),
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'authenticationTheme' => ['required', Rule::in(array_keys(Option::authenticationPageThemeOptions()))],
            'formTheme' => ['required', Rule::in(array_keys(Option::productFormThemeOptions()))],
        ]);

        Option::setAuthenticationPageTheme($this->authenticationTheme);
        Option::setProductFormTheme($this->formTheme);
        $this->fillFromSettings();
        $this->markSaved('Theme settings saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetThemeSettingsToDefault();
        $this->fillFromSettings();
        $this->completeResetWithNotice('Theme settings reset to defaults.');
    }

    public function updated(): void
    {
        $this->clearSavedNotice();
    }

    private function fillFromSettings(): void
    {
        $this->authenticationTheme = Option::authenticationPageTheme();
        $this->formTheme = Option::productFormTheme();
        $this->clearSavedNotice();
    }
}
