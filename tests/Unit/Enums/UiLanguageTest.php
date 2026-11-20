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
        $this->assertSame('English', $options['en']);
        $this->assertSame('日本語', $options['ja']);
        $this->assertSame('繁體中文', $options['zh_TW']);
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
