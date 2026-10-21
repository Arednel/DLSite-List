<?php

namespace Tests\Unit\Enums;

use App\Enums\UiLanguage;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class UiLanguageTest extends TestCase
{
    public function test_it_exposes_supported_language_options(): void
    {
        $this->assertSame([
            UiLanguage::English->value => 'English',
            UiLanguage::Japanese->value => '日本語',
            UiLanguage::TraditionalChinese->value => '繁體中文',
        ], UiLanguage::options());
    }

    public function test_current_resolves_the_application_locale_with_english_fallback(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame(UiLanguage::Japanese, UiLanguage::current());

        App::setLocale(UiLanguage::TraditionalChinese->value);

        $this->assertSame(UiLanguage::TraditionalChinese, UiLanguage::current());

        App::setLocale('unsupported');

        $this->assertSame(UiLanguage::English, UiLanguage::current());
    }
}
