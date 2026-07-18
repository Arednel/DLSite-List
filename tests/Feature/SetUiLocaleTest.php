<?php

namespace Tests\Feature;

use App\Enums\UiLanguage;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class SetUiLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_requests_use_the_saved_ui_language(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);

        $this->get(route('options.index'))
            ->assertOk();

        $this->assertSame(UiLanguage::Japanese->value, App::currentLocale());
    }

    public function test_web_requests_fall_back_to_english_for_an_invalid_saved_language(): void
    {
        Option::query()->create([
            'key' => Option::UI_LANGUAGE,
            'value' => 'unsupported',
        ]);
        App::setLocale(UiLanguage::Japanese->value);

        $this->get(route('options.index'))
            ->assertOk();

        $this->assertSame(UiLanguage::English->value, App::currentLocale());
    }

}
