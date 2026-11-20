<?php

namespace Tests\Unit;

use App\Enums\UiLanguage;
use Illuminate\Support\Arr;
use Tests\TestCase;

class TraditionalChineseCatalogTest extends TestCase
{
    public function test_framework_validation_rules_have_chinese_translations(): void
    {
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $chinese = Arr::dot(require lang_path('zh_TW/validation.php'));
        foreach (Arr::dot(Arr::except($english, ['custom', 'attributes'])) as $rule => $message) {
            $this->assertNotSame($message, $chinese[$rule], $rule);
        }
    }

    public function test_operational_errors_and_counted_messages_translate_with_missing_key_output_for_unknown_errors(): void
    {
        $englishErrors = require lang_path('en/errors.php');
        app()->setLocale(UiLanguage::TraditionalChinese->value);

        $this->assertNotSame(
            Arr::get($englishErrors, 'dlsite.work.geo_blocked'),
            __('errors.dlsite.work.geo_blocked'),
        );
        $this->assertSame('errors.RAW_DIAGNOSTIC_TOKEN', __('errors.RAW_DIAGNOSTIC_TOKEN'));

        foreach ([0, 1, 2, 500] as $count) {
            $message = trans_choice('errors.auth.login_throttled', $count, ['seconds' => $count]);

            $this->assertStringContainsString((string) $count, $message);
            $this->assertStringNotContainsString(':seconds', $message);
            $this->assertStringNotContainsString('|', $message);
            $this->assertStringNotContainsString('seconds', $message);
        }
    }
}
