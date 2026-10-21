<?php

namespace Tests\Unit\Enums;

use App\Enums\FetchedTagLanguage;
use App\Enums\UiLanguage;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class FetchedTagLanguageTest extends TestCase
{
    public function test_it_exposes_supported_fetched_tag_languages(): void
    {
        $this->assertSame([
            FetchedTagLanguage::English->value => 'English',
            FetchedTagLanguage::Japanese->value => '日本語',
        ], FetchedTagLanguage::options());
    }


    public function test_option_labels_are_not_translated_with_the_ui_locale(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame([
            FetchedTagLanguage::English->value => 'English',
            FetchedTagLanguage::Japanese->value => '日本語',
        ], FetchedTagLanguage::options());
    }

    public function test_it_maps_ui_languages(): void
    {
        $this->assertSame(FetchedTagLanguage::English, FetchedTagLanguage::forUiLanguage(UiLanguage::English));
        $this->assertSame(FetchedTagLanguage::Japanese, FetchedTagLanguage::forUiLanguage(UiLanguage::Japanese));
        $this->assertSame(FetchedTagLanguage::English, FetchedTagLanguage::forUiLanguage(UiLanguage::TraditionalChinese));
    }
}
