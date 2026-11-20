<?php

namespace Tests\Unit\Enums;

use App\Enums\DlsiteLanguage;
use App\Enums\UiLanguage;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Tests\LanguageCases;
use Tests\TestCase;

class DlsiteLanguageTest extends TestCase
{
    public function test_supported_codes_and_upstream_api_locales(): void
    {
        $expected = ['ja' => null, 'en' => 'en_US', 'zh_TW' => 'zh_TW'];

        $this->assertEqualsCanonicalizing(array_keys($expected), DlsiteLanguage::values());
        $this->assertSame(array_column(DlsiteLanguage::cases(), 'value'), DlsiteLanguage::values());
        foreach ($expected as $locale => $apiLocale) {
            $this->assertSame($apiLocale, DlsiteLanguage::from($locale)->apiLocale(), $locale);
        }
    }

    #[DataProviderExternal(LanguageCases::class, 'ui')]
    public function test_options_use_the_display_names_in_the_selected_catalog(UiLanguage $ui): void
    {
        App::setLocale($ui->value);
        $catalog = File::json(lang_path("{$ui->value}.json"));
        $expected = [];
        foreach (DlsiteLanguage::cases() as $language) {
            $expected[$language->value] = $catalog[$language->name];
        }

        $this->assertSame($expected, DlsiteLanguage::options());
    }

    public function test_metadata_language_options_use_the_reviewed_labels(): void
    {
        $reviewedLabels = [
            'en' => ['ja' => 'Japanese', 'en' => 'English', 'zh_TW' => 'Traditional Chinese'],
            'ja' => ['ja' => '日本語', 'en' => '英語', 'zh_TW' => '繁体字中国語'],
            'zh_TW' => ['ja' => '日文', 'en' => '英文', 'zh_TW' => '繁體中文'],
        ];

        foreach ($reviewedLabels as $uiLocale => $labels) {
            App::setLocale($uiLocale);
            $options = DlsiteLanguage::options();

            foreach ($labels as $code => $label) {
                $this->assertSame($label, $options[$code] ?? null, "{$uiLocale} label for {$code}");
            }
        }
    }
}
