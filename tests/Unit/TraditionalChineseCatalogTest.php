<?php

namespace Tests\Unit;

use App\Enums\UiLanguage;
use App\Support\Localization\ErrorMessage;
use Illuminate\Support\Arr;
use Tests\TestCase;

class TraditionalChineseCatalogTest extends TestCase
{
    public function test_traditional_chinese_covers_the_ui_and_operational_catalogs_without_losing_placeholders(): void
    {
        foreach (['.json', '/errors.php'] as $suffix) {
            $english = $this->catalog('en', $suffix);
            $chinese = $this->catalog(UiLanguage::TraditionalChinese->value, $suffix);

            $this->assertEqualsCanonicalizing(array_keys($english), array_keys($chinese));

            foreach ($english as $key => $value) {
                $this->assertNotSame('', trim($chinese[$key]), $key);
                $sourceForms = explode('|', $value);
                $translatedForms = explode('|', $chinese[$key]);

                // Chinese does not require a separate grammatical plural form.
                foreach ($sourceForms as $form) {
                    foreach ($translatedForms as $translation) {
                        $this->assertSame($this->placeholders($form), $this->placeholders($translation), $key);
                    }
                }
            }
        }
    }

    public function test_operational_errors_and_counted_messages_translate_without_touching_raw_diagnostics(): void
    {
        app()->setLocale(UiLanguage::TraditionalChinese->value);

        $this->assertNotSame('GeoBlocked DLSite work', ErrorMessage::display('GeoBlocked DLSite work'));
        $this->assertSame('RAW_DIAGNOSTIC_TOKEN', ErrorMessage::display('RAW_DIAGNOSTIC_TOKEN'));

        foreach ([0, 1, 2, 500] as $count) {
            $message = trans_choice('errors.login_throttled', $count, ['seconds' => $count]);

            $this->assertStringContainsString((string) $count, $message);
            $this->assertStringNotContainsString(':seconds', $message);
            $this->assertStringNotContainsString('|', $message);
            $this->assertStringNotContainsString('seconds', $message);
        }
    }

    public function test_framework_validation_rules_have_local_messages_and_preserve_replacements(): void
    {
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $chinese = require lang_path('zh_TW/validation.php');
        $rules = Arr::dot(Arr::except($english, ['custom', 'attributes']));

        foreach ($rules as $rule => $message) {
            $translation = Arr::get($chinese, $rule);

            $this->assertIsString($translation, $rule);
            $this->assertNotSame($message, $translation, $rule);
            $this->assertSame($this->placeholders($message), $this->placeholders($translation), $rule);
        }
    }

    private function catalog(string $locale, string $suffix): array
    {
        return $suffix === '.json'
            ? json_decode(file_get_contents(lang_path($locale . $suffix)), true, 512, JSON_THROW_ON_ERROR)
            : require lang_path($locale . $suffix);
    }

    private function placeholders(string $value): array
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
        sort($matches[0]);

        return $matches[0];
    }
}
