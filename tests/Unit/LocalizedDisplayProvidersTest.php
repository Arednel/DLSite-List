<?php

namespace Tests\Unit;

use App\Enums\ProductField;
use App\Enums\ProductIndexSortField;
use App\Enums\ProductProgress;
use App\Enums\UiLanguage;
use App\Models\Option;
use App\Support\PartialDateFormatter;
use App\Support\ProductFieldLayout;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocalizedDisplayProvidersTest extends TestCase
{
    protected function tearDown(): void
    {
        App::setLocale(UiLanguage::English->value);

        parent::tearDown();
    }

    public function test_representative_display_labels_follow_the_locale_without_changing_backed_values(): void
    {
        App::setLocale(UiLanguage::English->value);
        $this->assertSame('Plan to Listen', ProductProgress::PlanToListen->label());
        $this->assertSame('Fetched EN Tags', ProductField::FetchedTags->label());
        $this->assertSame('RJ / Title', ProductIndexSortField::RJ->label());

        App::setLocale(UiLanguage::Japanese->value);
        $this->assertSame('聴取予定', ProductProgress::PlanToListen->label());
        $this->assertSame('取得済みJPタグ', ProductField::FetchedTags->label());
        $this->assertSame('RJ / タイトル', ProductIndexSortField::RJ->label());

        $this->assertSame('Plan to Listen', ProductProgress::PlanToListen->value);
        $this->assertSame('fetched_tags', ProductField::FetchedTags->value);
        $this->assertSame('rj', ProductIndexSortField::RJ->value);
    }

    public function test_option_display_providers_translate_copy_but_keep_values_and_theme_brands_stable(): void
    {
        App::setLocale(UiLanguage::English->value);
        $this->assertSame([
            Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT => 'Follow redirect',
            Option::PRODUCT_FORM_MODAL_COMPLETION_REFRESH => 'Refresh current page',
            Option::PRODUCT_FORM_MODAL_COMPLETION_CLOSE => 'Close modal only',
        ], Option::productFormModalCompletionOptions());

        App::setLocale(UiLanguage::Japanese->value);
        $this->assertSame([
            Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT => 'リダイレクト先へ移動',
            Option::PRODUCT_FORM_MODAL_COMPLETION_REFRESH => '現在のページを再読み込み',
            Option::PRODUCT_FORM_MODAL_COMPLETION_CLOSE => 'モーダルのみ閉じる',
        ], Option::productFormModalCompletionOptions());
        $this->assertSame([
            Option::PRODUCT_FORM_THEME_CHERRY => 'Cherry',
            Option::PRODUCT_FORM_THEME_BLACK => 'Black',
        ], Option::productFormThemeOptions());
        $this->assertSame('redirect', Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT);
        $this->assertSame('cherry', Option::PRODUCT_FORM_THEME_CHERRY);
    }

    public function test_field_layout_storage_omits_locale_dependent_display_metadata(): void
    {
        $stored = ProductFieldLayout::storageLayout([
            [
                'field' => ProductField::Notes->value,
                'label' => 'Notes',
                'note' => 'Displayed note',
                'visible' => true,
            ],
        ], ProductFieldLayout::SURFACE_INDEX);
        $storedNotes = collect($stored)->firstWhere('field', ProductField::Notes->value);

        $this->assertArrayNotHasKey('label', $storedNotes);
        $this->assertArrayNotHasKey('note', $storedNotes);
    }

    public function test_partial_dates_follow_the_active_locale(): void
    {
        App::setLocale(UiLanguage::English->value);
        $this->assertSame('Year: 2026, Month: 01, Day: 02', PartialDateFormatter::format([
            'year' => 2026,
            'month' => '01',
            'day' => '02',
        ]));

        App::setLocale(UiLanguage::Japanese->value);
        $this->assertSame('年: 2026, 月: 01, 日: 02', PartialDateFormatter::format([
            'year' => 2026,
            'month' => '01',
            'day' => '02',
        ]));
    }
}
