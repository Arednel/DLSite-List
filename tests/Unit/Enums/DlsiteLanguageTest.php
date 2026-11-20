<?php

namespace Tests\Unit\Enums;

use App\Enums\DlsiteLanguage;
use App\Enums\UiLanguage;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Tests\Fixtures\DlsiteLanguageLabels;
use Tests\TestCase;

class DlsiteLanguageTest extends TestCase
{
    public function test_supported_codes_and_upstream_api_locales(): void
    {
        $expected = [
            'ja' => null,
            'en' => 'en_US',
            'zh_CN' => 'zh_CN',
            'zh_TW' => 'zh_TW',
            'ko' => 'ko_KR',
            'de' => 'de_DE',
            'fr' => 'fr_FR',
            'es' => 'es_ES',
            'it' => 'it_IT',
            'pt_BR' => 'pt_BR',
            'sv' => 'sv_SE',
            'id' => 'id_ID',
            'th' => 'th_TH',
            'vi' => 'vi_VN',
        ];

        $this->assertEqualsCanonicalizing(array_keys($expected), DlsiteLanguage::values());
        $this->assertSame(array_column(DlsiteLanguage::cases(), 'value'), DlsiteLanguage::values());
        foreach ($expected as $locale => $apiLocale) {
            $this->assertSame($apiLocale, DlsiteLanguage::from($locale)->apiLocale(), $locale);
        }
    }

    #[DataProviderExternal(DlsiteLanguageLabels::class, 'cases')]
    public function test_metadata_language_options_use_reviewed_catalog_labels(
        string $uiLocale,
        array $reviewedLabels,
    ): void {
        $uiLanguage = UiLanguage::from($uiLocale);
        App::setLocale($uiLocale);

        $catalog = File::json(lang_path("{$uiLocale}.json"));
        $catalogLabels = [];
        foreach (DlsiteLanguage::cases() as $language) {
            $catalogLabels[$language->value] = $catalog[$language->name];
        }

        $this->assertSame($reviewedLabels, $catalogLabels, $uiLocale);
        $this->assertSame($reviewedLabels, DlsiteLanguage::options(), $uiLocale);

        $metadataLanguage = $uiLanguage->dlsiteLanguage();
        $this->assertNotNull($metadataLanguage, $uiLocale);
        $this->assertSame($uiLanguage->label(), $reviewedLabels[$metadataLanguage->value], $uiLocale);
    }
}
