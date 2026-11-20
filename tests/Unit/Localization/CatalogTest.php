<?php

namespace Tests\Unit\Localization;

use App\Enums\DlsiteLanguage;
use App\Enums\UiLanguage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Tests\LanguageCases;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    #[DataProviderExternal(LanguageCases::class, 'ui')]
    public function test_ui_and_error_catalogs_preserve_keys_and_placeholders(UiLanguage $language): void
    {
        $ui = File::json(lang_path("{$language->value}.json"));
        $errors = Arr::dot(require lang_path("{$language->value}/errors.php"));

        $this->assertArrayNotHasKey('errors', $ui);
        $this->assertSame([], array_intersect_key($ui, $errors));
        foreach (array_keys($errors) as $key) {
            $this->assertMatchesRegularExpression('/\A[a-z0-9_]+(?:\.[a-z0-9_]+)*\z/', $key);
        }

        $this->assertCatalogMatches(File::json(lang_path('en.json')), $ui);
        $this->assertCatalogMatches(Arr::dot(require lang_path('en/errors.php')), $errors);
    }

    #[DataProviderExternal(LanguageCases::class, 'ui')]
    public function test_validation_catalog_covers_framework_rules_and_project_overrides(UiLanguage $language): void
    {
        $framework = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $overrides = require lang_path('en/validation.php');
        $expected = Arr::dot(array_replace_recursive(Arr::except($framework, ['custom', 'attributes']), $overrides));
        $actual = require lang_path("{$language->value}/validation.php");
        if ($language === UiLanguage::English) {
            $actual = array_replace_recursive($framework, $actual);
        }

        $this->assertCatalogMatches($expected, Arr::only(Arr::dot($actual), array_keys($expected)));
    }

    #[DataProviderExternal(LanguageCases::class, 'translatedUi')]
    public function test_translated_validation_catalogs_use_the_same_project_attribute_keys(UiLanguage $language): void
    {
        $expected = array_keys((require lang_path('ja/validation.php'))['attributes']);
        $actual = (require lang_path("{$language->value}/validation.php"))['attributes'] ?? [];

        $this->assertEqualsCanonicalizing($expected, array_keys($actual), $language->value);
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $actual, "{$language->value}: {$key}");
            $this->assertIsString($actual[$key], "{$language->value}: {$key}");
            $this->assertNotSame('', trim($actual[$key]), "{$language->value}: {$key}");
        }
    }

    public function test_english_display_overrides(): void
    {
        $englishDisplayOverrides = [
            'Custom->Fetched' => 'Custom -> Fetched',
            'Custom->Fetched JP' => 'Custom -> Fetched JP',
            'Custom->Fetched EN' => 'Custom -> Fetched EN',
            'This work Custom->Fetched' => 'This work Custom -> Fetched',
            'Announcement Date from' => 'Scheduled release date from',
            'Announcement Date to' => 'Scheduled release date to',
            'Announcement Date' => 'Scheduled release date',
        ];
        $english = File::json(lang_path('en.json'));
        $actual = array_filter($english, fn(string $value, string $key): bool => $key !== $value, ARRAY_FILTER_USE_BOTH);

        $this->assertEquals($englishDisplayOverrides, Arr::except($actual, array_column(DlsiteLanguage::cases(), 'name')));
    }

    private function assertCatalogMatches(array $expected, array $actual): void
    {
        $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($actual));
        foreach ($expected as $key => $text) {
            $this->assertIsString($actual[$key], $key);
            $this->assertNotSame('', trim($actual[$key]), $key);
            $this->assertSame($this->placeholders($text), $this->placeholders($actual[$key]), $key);
        }
    }

    private function placeholders(string $text): array
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $text, $matches);
        $placeholders = array_unique($matches[0]);
        sort($placeholders);

        return $placeholders;
    }
}
