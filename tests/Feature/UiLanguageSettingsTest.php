<?php

namespace Tests\Feature;

use App\Enums\DlsiteLanguage;
use App\Enums\UiLanguage;
use App\Livewire\UiLanguageSettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Tests\LanguageCases;
use Tests\TestCase;

class UiLanguageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_mounts_with_default_language_settings_and_renders_supported_options(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->assertSet('language', UiLanguage::English->value)
            ->assertSet('followUiLanguage', true)
            ->assertSet('metadataLanguage', DlsiteLanguage::English->value)
            ->assertSet('fetchLanguages', [DlsiteLanguage::English->value])
            ->assertSee('Follow UI language automatically')
            ->assertSee('Preferred metadata language')
            ->assertSee('DLsite metadata languages')
            ->assertDontSee('DLsite metadata fetch settings apply together to Title, Description, and Fetched Tags. Japanese is always fetched and stored.')
            ->assertDontSee('Used as the preferred DLsite metadata language when available.')
            ->assertSee('class="metadata-language-block"', false)
            ->assertSee('class="field-layout-section field-layout-collapsible metadata-language-nested"', false)
            ->assertSee('Fetched languages')
            ->assertSee(array_values(UiLanguage::options()))
            ->assertSee(array_values(DlsiteLanguage::options()));
    }

    #[DataProviderExternal(LanguageCases::class, 'ui')]
    public function test_saved_ui_language_localizes_the_page_and_selects_matching_metadata(UiLanguage $ui): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', $ui->value)
            ->set('fetchLanguages', [])
            ->call('save')
            ->assertHasNoErrors();

        $matching = DlsiteLanguage::tryFrom($ui->value);
        $this->assertSame($ui, Option::uiLanguage());
        $this->assertSame($matching, $ui->dlsiteLanguage());
        $this->assertSame($matching ?? DlsiteLanguage::English, Option::effectiveDlsiteMetadataLanguage());
        $this->assertContains($matching ?? DlsiteLanguage::English, Option::effectiveDlsiteMetadataFetchLanguages());

        $catalog = File::json(lang_path("{$ui->value}.json"));
        $htmlLanguage = str_replace('_', '-', $ui->value);
        $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('<html lang="' . $htmlLanguage . '">', false)
            ->assertSee($catalog['Language settings saved.']);
    }

    public function test_follow_mode_uses_the_current_ui_language_for_display_and_fetch(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', UiLanguage::Japanese->value)
            ->assertSet('metadataLanguage', DlsiteLanguage::English->value)
            ->assertSee('value="ja" selected', false)
            ->assertSee('id="metadata-language"', false)
            ->assertSee('aria-label="Preferred metadata language" disabled', false)
            ->assertSee('Always fetched')
            ->assertSee('wire:model.live="fetchLanguages" value="en"', false);
    }

    public function test_it_rejects_unsupported_language_settings_without_persisting_them(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', 'unsupported')
            ->set('metadataLanguage', 'unsupported')
            ->set('fetchLanguages', ['unsupported'])
            ->call('save')
            ->assertHasErrors(['language', 'metadataLanguage', 'fetchLanguages.0'])
            ->assertNoRedirect();

        $this->assertSame(UiLanguage::English, Option::uiLanguage());
        $this->assertTrue(Option::dlsiteMetadataLanguageFollowsUi());
        $this->assertSame(DlsiteLanguage::English, Option::dlsiteMetadataLanguage());
        $this->assertSame([DlsiteLanguage::English], Option::dlsiteMetadataFetchLanguages());
        $this->assertDatabaseMissing('options', ['key' => Option::UI_LANGUAGE]);
        $this->assertDatabaseMissing('options', ['key' => Option::DLSITE_METADATA_LANGUAGE_FOLLOWS_UI]);
        $this->assertDatabaseMissing('options', ['key' => Option::DLSITE_METADATA_LANGUAGE]);
        $this->assertDatabaseMissing('options', ['key' => Option::DLSITE_METADATA_FETCH_LANGUAGES]);
    }

    public function test_it_rejects_japanese_and_non_list_optional_fetch_languages(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('fetchLanguages', [DlsiteLanguage::Japanese->value])
            ->call('save')
            ->assertHasErrors('fetchLanguages.0')
            ->assertNoRedirect();

        Livewire::test(UiLanguageSettings::class)
            ->set('fetchLanguages', ['language' => DlsiteLanguage::English->value])
            ->call('save')
            ->assertHasErrors('fetchLanguages')
            ->assertNoRedirect();

        $this->assertSame([DlsiteLanguage::English], Option::dlsiteMetadataFetchLanguages());
        $this->assertDatabaseMissing('options', ['key' => Option::DLSITE_METADATA_FETCH_LANGUAGES]);
    }

    #[DataProviderExternal(LanguageCases::class, 'metadata')]
    public function test_save_can_select_metadata_language_independently_from_the_ui_language(DlsiteLanguage $language): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', UiLanguage::Japanese->value)
            ->set('followUiLanguage', false)
            ->set('metadataLanguage', $language->value)
            ->set('fetchLanguages', [])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('options.index', ['tab' => 'general']);

        $this->assertSame(UiLanguage::Japanese, Option::uiLanguage());
        $this->assertFalse(Option::dlsiteMetadataLanguageFollowsUi());
        $this->assertSame($language, Option::dlsiteMetadataLanguage());
        $this->assertSame($language, Option::effectiveDlsiteMetadataLanguage());
        $this->assertSame(
            $language === DlsiteLanguage::Japanese ? [$language] : [DlsiteLanguage::Japanese, $language],
            Option::effectiveDlsiteMetadataFetchLanguages(),
        );
        $this->assertSame('Language settings saved.', session('language_settings_notice'));
        $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('言語設定を保存しました。')
            ->assertDontSee('Language settings saved.');
    }

    public function test_follow_mode_uses_ui_mapping_without_overwriting_manual_choices(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', UiLanguage::Japanese->value)
            ->set('followUiLanguage', true)
            ->set('metadataLanguage', DlsiteLanguage::English->value)
            ->set('fetchLanguages', [DlsiteLanguage::English->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(DlsiteLanguage::English, Option::dlsiteMetadataLanguage());
        $this->assertSame([DlsiteLanguage::English], Option::dlsiteMetadataFetchLanguages());
        $this->assertSame(DlsiteLanguage::Japanese, Option::effectiveDlsiteMetadataLanguage());
        $this->assertSame(
            [DlsiteLanguage::Japanese, DlsiteLanguage::English],
            Option::effectiveDlsiteMetadataFetchLanguages(),
        );
    }

    public function test_individual_reset_removes_all_language_options_and_redirects_with_a_distinct_flash_notice(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);
        Option::setDlsiteMetadataLanguageFollowsUi(false);
        Option::setDlsiteMetadataLanguage(DlsiteLanguage::Japanese);
        Option::setDlsiteMetadataFetchLanguages([]);

        Livewire::test(UiLanguageSettings::class)
            ->assertSet('language', UiLanguage::Japanese->value)
            ->assertSet('followUiLanguage', false)
            ->assertSet('metadataLanguage', DlsiteLanguage::Japanese->value)
            ->assertSet('fetchLanguages', [])
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertRedirectToRoute('options.index', ['tab' => 'general']);

        $this->assertSame(UiLanguage::English, Option::uiLanguage());
        $this->assertTrue(Option::dlsiteMetadataLanguageFollowsUi());
        $this->assertSame(DlsiteLanguage::English, Option::dlsiteMetadataLanguage());
        $this->assertSame([DlsiteLanguage::English], Option::dlsiteMetadataFetchLanguages());
        $this->assertDatabaseMissing('options', ['key' => Option::UI_LANGUAGE]);
        $this->assertDatabaseMissing('options', ['key' => Option::DLSITE_METADATA_LANGUAGE_FOLLOWS_UI]);
        $this->assertDatabaseMissing('options', ['key' => Option::DLSITE_METADATA_LANGUAGE]);
        $this->assertDatabaseMissing('options', ['key' => Option::DLSITE_METADATA_FETCH_LANGUAGES]);
        $this->assertSame('Language settings reset to default.', session('language_settings_notice'));
        $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Language settings reset to default.');
    }
}
