<?php

namespace Tests\Unit\Enums;

use App\Enums\UiLanguage;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class UiLanguageTest extends TestCase
{
    public function test_it_exposes_supported_language_options(): void
    {
        $options = UiLanguage::options();
        $this->assertSame(array_column(UiLanguage::cases(), 'value'), array_keys($options));
        $this->assertSame([
            'en' => 'English',
            'ja' => '日本語',
            'zh_CN' => '简体中文',
            'zh_TW' => '繁體中文',
            'ko' => '한국어',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'es' => 'Español',
            'it' => 'Italiano',
            'pt_BR' => 'Português',
            'sv' => 'Svenska',
            'id' => 'Bahasa Indonesia',
            'th' => 'ภาษาไทย',
            'vi' => 'Tiếng Việt',
        ], $options);
        foreach ($options as $label) {
            $this->assertNotSame('', trim($label));
        }
    }

    public function test_current_resolves_the_application_locale_with_english_fallback(): void
    {
        foreach (UiLanguage::cases() as $language) {
            App::setLocale($language->value);
            $this->assertSame($language, UiLanguage::current());
        }

        App::setLocale('unsupported');

        $this->assertSame(UiLanguage::English, UiLanguage::current());
    }
}
