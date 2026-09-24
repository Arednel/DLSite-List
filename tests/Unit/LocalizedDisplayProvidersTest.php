<?php

namespace Tests\Unit;

use App\Enums\ContentFocus;
use App\Enums\ProductField;
use App\Enums\ProductIndexSortField;
use App\Enums\ProductProgress;
use App\Enums\UiLanguage;
use App\Models\Option;
use App\Support\ContentTerminology;
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
        $this->app->instance(ContentTerminology::class, new ContentTerminology(ContentFocus::Listening));

        App::setLocale(UiLanguage::English->value);
        $this->assertSame('Plan to Listen', ProductProgress::PlanToListen->label());
        $this->assertSame('On Hold', ProductProgress::OnHold->label());
        $this->assertSame('Dropped', ProductProgress::Dropped->label());
        $this->assertSame('Fetched EN Tags', ProductField::FetchedTags->label());
        $this->assertSame('RJ Code', ProductIndexSortField::RJ->label());

        App::setLocale(UiLanguage::Japanese->value);
        $this->assertSame('聴取予定', ProductProgress::PlanToListen->label());
        $this->assertSame('保留', ProductProgress::OnHold->label());
        $this->assertSame('中止', ProductProgress::Dropped->label());
        $this->assertSame('取得済みJPタグ', ProductField::FetchedTags->label());
        $this->assertSame('RJコード', ProductIndexSortField::RJ->label());

        $this->assertSame('Plan to Listen', ProductProgress::PlanToListen->value);
        $this->assertSame('On Hold', ProductProgress::OnHold->value);
        $this->assertSame('Dropped', ProductProgress::Dropped->value);
        $this->assertSame('fetched_tags', ProductField::FetchedTags->value);
        $this->assertSame('rj', ProductIndexSortField::RJ->value);
    }


    public function test_content_focus_options_use_dlsite_specific_category_examples_in_both_languages(): void
    {
        App::setLocale(UiLanguage::English->value);
        $this->assertSame([
            'general' => 'General',
            'listening' => 'Listening (Voice / ASMR / Voice Dramas)',
            'reading' => 'Reading (Manga / Comics / Light Novels / Novels / Books)',
            'games' => 'Games (Games, PC Games)',
            'video' => 'Video (Anime / Videos)',
            'music' => 'Music (Music)',
            'artwork' => 'Artwork (CG)',
        ], ContentFocus::options());

        App::setLocale(UiLanguage::Japanese->value);
        $this->assertSame([
            'general' => '一般',
            'listening' => '聴取（ボイス・ASMR / ドラマCD）',
            'reading' => '読書（マンガ / コミック / ラノベ / 小説 / 一般書籍）',
            'games' => 'ゲーム（ゲーム、PCソフト）',
            'video' => '動画（アニメ / 動画）',
            'music' => '音楽（音楽）',
            'artwork' => 'アートワーク（CG）',
        ], ContentFocus::options());
    }

    public function test_content_focus_profiles_change_only_their_presentation_wording(): void
    {
        App::setLocale(UiLanguage::English->value);

        $expectations = [
            ContentFocus::General->value => ['All Works', 'In Progress', 'Planned', 'Total Times Repeated', 'Repeat Value'],
            ContentFocus::Listening->value => ['All ASMR', 'Listening', 'Plan to Listen', 'Total Times Re-listened', 'Re-listen Value'],
            ContentFocus::Reading->value => ['All Works', 'Reading', 'Plan to Read', 'Total Times Re-read', 'Re-read Value'],
            ContentFocus::Games->value => ['All Games', 'Playing', 'Plan to Play', 'Total Times Replayed', 'Replay Value'],
            ContentFocus::Video->value => ['All Videos', 'Watching', 'Plan to Watch', 'Total Times Rewatched', 'Rewatch Value'],
            ContentFocus::Music->value => ['All Music', 'Listening', 'Plan to Listen', 'Total Times Re-listened', 'Re-listen Value'],
            ContentFocus::Artwork->value => ['All Artwork', 'Viewing', 'Plan to View', 'Total Times Revisited', 'Revisit Value'],
        ];

        foreach (ContentFocus::cases() as $focus) {
            $terminology = new ContentTerminology($focus);
            [$allWorks, $active, $planned, $repeatCount, $repeatValue] = $expectations[$focus->value];

            $this->assertSame($allWorks, $terminology->allWorks());
            $this->assertSame($active, $terminology->progress(ProductProgress::Listening));
            $this->assertSame($planned, $terminology->progress(ProductProgress::PlanToListen));
            $this->assertSame($repeatCount, $terminology->repeatCount());
            $this->assertSame($repeatValue, $terminology->repeatValue());
        }

        $this->assertSame('Listening', ProductProgress::Listening->value);
        $this->assertSame('Plan to Listen', ProductProgress::PlanToListen->value);
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
