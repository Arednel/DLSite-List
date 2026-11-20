<?php

namespace Tests\Unit\Enums;

use App\Enums\DlsiteLanguage;
use App\Enums\UiLanguage;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class DlsiteLanguageTest extends TestCase
{
    public function test_values_returns_canonical_language_codes_in_case_order(): void
    {
        $this->assertSame(['ja', 'en'], DlsiteLanguage::values());
    }

    public function test_it_exposes_currently_supported_dlsite_languages(): void
    {
        $this->assertSame([
            DlsiteLanguage::Japanese->value => 'Japanese',
            DlsiteLanguage::English->value => 'English',
        ], DlsiteLanguage::options());
    }

    public function test_it_exposes_the_upstream_api_locale_mapping(): void
    {
        $this->assertNull(DlsiteLanguage::Japanese->apiLocale());
        $this->assertSame('en_US', DlsiteLanguage::English->apiLocale());
    }

    public function test_labels_follow_the_current_ui_locale(): void
    {
        foreach (
            [
                UiLanguage::English->value => ['ja' => 'Japanese', 'en' => 'English'],
                UiLanguage::Japanese->value => ['ja' => '日本語', 'en' => '英語'],
                UiLanguage::TraditionalChinese->value => ['ja' => '日文', 'en' => '英文'],
            ] as $locale => $expected
        ) {
            App::setLocale($locale);
            $this->assertSame($expected, DlsiteLanguage::options());
        }
    }

    public function test_ui_languages_map_only_when_dlsite_support_is_enabled(): void
    {
        $this->assertSame(DlsiteLanguage::English, UiLanguage::English->dlsiteLanguage());
        $this->assertSame(DlsiteLanguage::Japanese, UiLanguage::Japanese->dlsiteLanguage());
        $this->assertNull(UiLanguage::TraditionalChinese->dlsiteLanguage());
    }
}
