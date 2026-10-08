<?php

namespace Tests\Feature;

use App\Enums\FetchedTagLanguage;
use App\Enums\UiLanguage;
use App\Livewire\UiLanguageSettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UiLanguageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_mounts_with_default_language_settings_and_renders_supported_options(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->assertSet('language', UiLanguage::English->value)
            ->assertSet('followFetchedTagsLanguage', true)
            ->assertSet('fetchedTagLanguage', FetchedTagLanguage::English->value)
            ->assertSee('Follow UI language automatically')
            ->assertSee('Fetched Tags language')
            ->assertSee('English')
            ->assertSee('日本語');
    }

    public function test_follow_mode_mirrors_the_current_ui_language_in_the_disabled_dropdown(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', UiLanguage::Japanese->value)
            ->assertSet('fetchedTagLanguage', FetchedTagLanguage::English->value)
            ->assertSee('value="jp" selected', false)
            ->assertSee('id="fetched-tag-language" class="option-control option-control-select" aria-label="Fetched Tags language" disabled', false);
    }

    public function test_it_rejects_unsupported_language_settings_without_persisting_them(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', 'unsupported')
            ->set('fetchedTagLanguage', 'unsupported')
            ->call('save')
            ->assertHasErrors(['language', 'fetchedTagLanguage'])
            ->assertNoRedirect();

        $this->assertSame(UiLanguage::English, Option::uiLanguage());
        $this->assertTrue(Option::fetchedTagLanguageFollowsUi());
        $this->assertSame(FetchedTagLanguage::English, Option::fetchedTagLanguage());
        $this->assertDatabaseMissing('options', ['key' => Option::UI_LANGUAGE]);
        $this->assertDatabaseMissing('options', ['key' => Option::FETCHED_TAG_LANGUAGE_FOLLOWS_UI]);
        $this->assertDatabaseMissing('options', ['key' => Option::FETCHED_TAG_LANGUAGE]);
    }

    public function test_save_can_select_fetched_tags_independently_from_the_ui_language(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', UiLanguage::Japanese->value)
            ->set('followFetchedTagsLanguage', false)
            ->set('fetchedTagLanguage', FetchedTagLanguage::English->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('options.index', ['tab' => 'general']);

        $this->assertSame(UiLanguage::Japanese, Option::uiLanguage());
        $this->assertFalse(Option::fetchedTagLanguageFollowsUi());
        $this->assertSame(FetchedTagLanguage::English, Option::fetchedTagLanguage());
        $this->assertSame(FetchedTagLanguage::English, Option::effectiveFetchedTagLanguage());
        $this->assertSame('Language settings saved.', session('language_settings_notice'));
        $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('言語設定を保存しました。')
            ->assertDontSee('Language settings saved.');
    }

    public function test_follow_mode_uses_the_current_ui_language_without_overwriting_the_manual_choice(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', UiLanguage::Japanese->value)
            ->set('followFetchedTagsLanguage', true)
            ->set('fetchedTagLanguage', FetchedTagLanguage::English->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(FetchedTagLanguage::English, Option::fetchedTagLanguage());
        app()->setLocale(UiLanguage::Japanese->value);
        $this->assertSame(FetchedTagLanguage::Japanese, Option::effectiveFetchedTagLanguage());
    }

    public function test_individual_reset_removes_all_language_options_and_redirects_with_a_distinct_flash_notice(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);
        Option::setFetchedTagLanguageFollowsUi(false);
        Option::setFetchedTagLanguage(FetchedTagLanguage::Japanese);

        Livewire::test(UiLanguageSettings::class)
            ->assertSet('language', UiLanguage::Japanese->value)
            ->assertSet('followFetchedTagsLanguage', false)
            ->assertSet('fetchedTagLanguage', FetchedTagLanguage::Japanese->value)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertRedirectToRoute('options.index', ['tab' => 'general']);

        $this->assertSame(UiLanguage::English, Option::uiLanguage());
        $this->assertTrue(Option::fetchedTagLanguageFollowsUi());
        $this->assertSame(FetchedTagLanguage::English, Option::fetchedTagLanguage());
        $this->assertDatabaseMissing('options', ['key' => Option::UI_LANGUAGE]);
        $this->assertDatabaseMissing('options', ['key' => Option::FETCHED_TAG_LANGUAGE_FOLLOWS_UI]);
        $this->assertDatabaseMissing('options', ['key' => Option::FETCHED_TAG_LANGUAGE]);
        $this->assertSame('Language settings reset to default.', session('language_settings_notice'));
        $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Language settings reset to default.');
    }
}
