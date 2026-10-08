<?php

namespace App\Livewire;

use App\Enums\FetchedTagLanguage;
use App\Enums\UiLanguage;
use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class UiLanguageSettings extends Component
{
    use ConfirmsOptionReset;

    private const NOTICE_SESSION_KEY = 'language_settings_notice';

    public string $language = UiLanguage::English->value;

    public bool $followFetchedTagsLanguage = true;

    public string $fetchedTagLanguage = FetchedTagLanguage::English->value;

    public function mount(): void
    {
        $this->language = Option::uiLanguage()->value;
        $this->followFetchedTagsLanguage = Option::fetchedTagLanguageFollowsUi();
        $this->fetchedTagLanguage = Option::fetchedTagLanguage()->value;
    }

    public function render(): View
    {
        $uiLanguage = UiLanguage::tryFrom($this->language) ?? Option::uiLanguage();

        return view('livewire.ui-language-settings', [
            'languageOptions' => UiLanguage::options(),
            'fetchedTagLanguageOptions' => FetchedTagLanguage::options(),
            'followedFetchedTagLanguage' => FetchedTagLanguage::forUiLanguage($uiLanguage)->value,
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'language' => ['required', Rule::enum(UiLanguage::class)],
            'followFetchedTagsLanguage' => ['boolean'],
            'fetchedTagLanguage' => ['required', Rule::enum(FetchedTagLanguage::class)],
        ]);

        Option::setUiLanguage($this->language);
        Option::setFetchedTagLanguageFollowsUi($this->followFetchedTagsLanguage);
        Option::setFetchedTagLanguage($this->fetchedTagLanguage);

        session()->flash(
            self::NOTICE_SESSION_KEY,
            'Language settings saved.',
        );

        $this->redirectRoute('options.index', ['tab' => 'general']);
    }

    public function resetToDefault(): void
    {
        Option::resetUiLanguageToDefault();
        Option::resetFetchedTagLanguageSettingsToDefault();

        session()->flash(
            self::NOTICE_SESSION_KEY,
            'Language settings reset to default.',
        );

        $this->redirectRoute('options.index', ['tab' => 'general']);
    }
}
