<?php

namespace Tests\Unit\Enums;

use App\Enums\UiLanguage;
use App\Models\Genre;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class UiLanguageTest extends TestCase
{
    public function test_it_exposes_supported_language_options(): void
    {
        $this->assertSame([
            UiLanguage::English->value => 'English',
            UiLanguage::Japanese->value => '日本語',
        ], UiLanguage::options());
    }

    public function test_current_resolves_the_application_locale_with_english_fallback(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame(UiLanguage::Japanese, UiLanguage::current());

        App::setLocale('unsupported');

        $this->assertSame(UiLanguage::English, UiLanguage::current());
    }

    public function test_it_maps_each_ui_language_to_its_fetched_tag_bucket_and_code(): void
    {
        $this->assertSame(Genre::LANGUAGE_ENGLISH, UiLanguage::English->fetchedTagLanguage());
        $this->assertSame('EN', UiLanguage::English->fetchedTagCode());
        $this->assertSame(Genre::LANGUAGE_JAPANESE, UiLanguage::Japanese->fetchedTagLanguage());
        $this->assertSame('JP', UiLanguage::Japanese->fetchedTagCode());
    }
}
