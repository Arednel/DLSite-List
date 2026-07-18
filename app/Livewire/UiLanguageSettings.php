<?php

namespace App\Livewire;

use App\Enums\UiLanguage;
use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class UiLanguageSettings extends Component
{
    use ConfirmsOptionReset;

    private const NOTICE_SESSION_KEY = 'ui_language_notice';

    public string $language = UiLanguage::English->value;

    public function mount(): void
    {
        $this->language = Option::uiLanguage()->value;
    }

    public function render(): View
    {
        return view('livewire.ui-language-settings', [
            'languageOptions' => UiLanguage::options(),
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'language' => ['required', Rule::enum(UiLanguage::class)],
        ]);

        Option::setUiLanguage($this->language);

        session()->flash(
            self::NOTICE_SESSION_KEY,
            'UI language setting saved.',
        );

        $this->redirectRoute('options.index', ['tab' => 'general']);
    }

    public function resetToDefault(): void
    {
        Option::resetUiLanguageToDefault();

        session()->flash(
            self::NOTICE_SESSION_KEY,
            'UI language reset to default.',
        );

        $this->redirectRoute('options.index', ['tab' => 'general']);
    }
}
