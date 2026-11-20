<?php

namespace App\Livewire;

use App\Enums\DlsiteLanguage;
use App\Enums\UiLanguage;
use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class UiLanguageSettings extends Component
{
    use ConfirmsOptionReset;

    private const NOTICE_SESSION_KEY = 'language_settings_notice';

    public string $language = UiLanguage::English->value;

    public bool $followUiLanguage = true;

    public string $metadataLanguage = DlsiteLanguage::English->value;

    /** @var list<string> */
    public array $fetchLanguages = [DlsiteLanguage::English->value];

    public function mount(): void
    {
        $this->language = Option::uiLanguage()->value;
        $this->followUiLanguage = Option::dlsiteMetadataLanguageFollowsUi();
        $this->metadataLanguage = Option::dlsiteMetadataLanguage()->value;
        $this->fetchLanguages = array_column(
            Option::dlsiteMetadataFetchLanguages(),
            'value',
        );
    }

    public function render(): View
    {
        $uiLanguage = UiLanguage::tryFrom($this->language) ?? Option::uiLanguage();
        $displayedMetadataLanguage = $this->followUiLanguage
            ? ($uiLanguage->dlsiteLanguage() ?? DlsiteLanguage::English)
            : (DlsiteLanguage::tryFrom($this->metadataLanguage) ?? DlsiteLanguage::English);
        $metadataLanguageOptions = DlsiteLanguage::options();

        return view('livewire.ui-language-settings', [
            'languageOptions' => UiLanguage::options(),
            'metadataLanguageOptions' => $metadataLanguageOptions,
            'optionalFetchLanguageOptions' => Arr::except($metadataLanguageOptions, [DlsiteLanguage::Japanese->value]),
            'alwaysFetchedLanguageLabel' => DlsiteLanguage::Japanese->label(),
            'displayedMetadataLanguage' => $displayedMetadataLanguage->value,
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'language' => ['required', Rule::enum(UiLanguage::class)],
            'followUiLanguage' => ['boolean'],
            'metadataLanguage' => ['required', Rule::enum(DlsiteLanguage::class)],
            'fetchLanguages' => ['array', 'list'],
            'fetchLanguages.*' => [
                'required',
                Rule::enum(DlsiteLanguage::class)->except(DlsiteLanguage::Japanese),
                'distinct:strict',
            ],
        ]);

        Option::setUiLanguage($this->language);
        Option::setDlsiteMetadataLanguageFollowsUi($this->followUiLanguage);
        Option::setDlsiteMetadataLanguage($this->metadataLanguage);
        Option::setDlsiteMetadataFetchLanguages($this->fetchLanguages);

        session()->flash(
            self::NOTICE_SESSION_KEY,
            'Language settings saved.',
        );

        $this->redirectRoute('options.index', ['tab' => 'general']);
    }

    public function resetToDefault(): void
    {
        Option::resetUiLanguageToDefault();
        Option::resetDlsiteMetadataLanguageSettingsToDefault();

        session()->flash(
            self::NOTICE_SESSION_KEY,
            'Language settings reset to default.',
        );

        $this->redirectRoute('options.index', ['tab' => 'general']);
    }
}
