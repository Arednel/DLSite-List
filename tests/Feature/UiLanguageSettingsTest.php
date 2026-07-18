<?php

namespace Tests\Feature;

use App\Enums\UiLanguage;
use App\Livewire\UiLanguageSettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UiLanguageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_mounts_with_english_and_renders_supported_language_options(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->assertSet('language', UiLanguage::English->value)
            ->assertSee('English')
            ->assertSee('日本語');
    }

    public function test_it_rejects_an_unsupported_language_without_persisting_it(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', 'unsupported')
            ->call('save')
            ->assertHasErrors('language')
            ->assertNoRedirect();

        $this->assertSame(UiLanguage::English, Option::uiLanguage());
        $this->assertDatabaseMissing('options', ['key' => Option::UI_LANGUAGE]);
    }

    public function test_save_persists_the_language_and_redirects_with_a_flash_notice(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', UiLanguage::Japanese->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('options.index', ['tab' => 'general']);

        $this->assertSame(UiLanguage::Japanese, Option::uiLanguage());
        $this->assertSame('UI language setting saved.', session('ui_language_notice'));
        $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('表示言語を保存しました。')
            ->assertDontSee('UI language setting saved.');
    }

    public function test_individual_reset_removes_the_option_and_redirects_with_a_distinct_flash_notice(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);

        Livewire::test(UiLanguageSettings::class)
            ->assertSet('language', UiLanguage::Japanese->value)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertRedirectToRoute('options.index', ['tab' => 'general']);

        $this->assertSame(UiLanguage::English, Option::uiLanguage());
        $this->assertDatabaseMissing('options', ['key' => Option::UI_LANGUAGE]);
        $this->assertSame('UI language reset to default.', session('ui_language_notice'));
        $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('UI language reset to default.');
    }
}
