<?php

namespace Tests\Unit\Support\Transfers;

use App\Enums\DlsiteLanguage;
use App\Enums\UiLanguage;
use App\Models\LibraryImportItem;
use App\Support\Transfers\ImportReview;
use App\Support\Transfers\ImportValue;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportValueTest extends TestCase
{
    public function test_metadata_catalogs_cover_supported_languages_and_attributes(): void
    {
        $expectedKeys = [
            ...array_column(DlsiteLanguage::cases(), 'name'),
            ':language Title',
            ':language Description',
            'Localized Title',
            'Localized Description',
            'Localized tags',
            'Localized titles',
            'Localized descriptions',
        ];

        foreach (UiLanguage::cases() as $language) {
            $catalog = File::json(lang_path("{$language->value}.json"));

            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $catalog);
                $this->assertIsString($catalog[$key], $key);
                $this->assertNotSame('', trim($catalog[$key]), $key);
            }
        }
    }

    public function test_localized_metadata_labels_follow_the_current_ui_catalog(): void
    {
        foreach (
            [
                'en' => ['Japanese', 'English', 'Japanese Title', 'English Title', 'Japanese Description', 'English Description'],
                'ja' => ['日本語', '英語', '日本語タイトル', '英語タイトル', '日本語の説明', '英語の説明'],
                'zh_TW' => ['日文', '英文', '日文標題', '英文標題', '日文說明', '英文說明'],
            ] as $locale => [$japanese, $english, $japaneseTitle, $englishTitle, $japaneseDescription, $englishDescription]
        ) {
            app()->setLocale($locale);

            $this->assertSame($japanese, ImportValue::label('ja'));
            $this->assertSame($english, ImportValue::label('en'));
            $this->assertSame($japaneseTitle, ImportValue::label('work_name'));
            $this->assertSame($englishTitle, ImportValue::label('localization:en:title'));
            $this->assertSame($japaneseDescription, ImportValue::label('description'));
            $this->assertSame($englishDescription, ImportValue::label('localization:en:description'));
        }
    }

    public function test_unrecognized_field_identifiers_keep_the_existing_label_fallback(): void
    {
        foreach (
            [
                'metadata' => 'Metadata',
                'localization:en' => 'Localization:en',
                'localization::title' => 'Localization::title',
                'localization:en:title:extra' => 'Localization:en:title:extra',
                'localization:en:notes' => 'Localization:en:notes',
                'other:en:title' => 'Other:en:title',
                'localization:zz:title' => 'Localization:zz:title',
            ] as $key => $label
        ) {
            $this->assertSame($label, ImportValue::label($key));
        }
    }

    public function test_new_work_title_and_description_labels_are_translated_in_each_ui_language(): void
    {
        $item = new LibraryImportItem([
            'library_transfer_run_id' => 42,
            'incoming' => [
                'titles' => ['ja' => 'Japanese title', 'en' => 'English title'],
                'descriptions' => ['ja' => 'Japanese description', 'en' => 'English description'],
            ],
        ]);

        foreach (
            [
                'en' => ['Japanese Title', 'English Title', 'Japanese Description', 'English Description'],
                'ja' => ['日本語タイトル', '英語タイトル', '日本語の説明', '英語の説明'],
                'zh_TW' => ['日文標題', '英文標題', '日文說明', '英文說明'],
            ] as $locale => $labels
        ) {
            app()->setLocale($locale);

            $changes = [
                ...ImportValue::newWorkChanges($item, 'titles'),
                ...ImportValue::newWorkChanges($item, 'descriptions'),
            ];

            $this->assertSame($labels, array_column($changes, 'label'));
            $this->assertSame(
                ['Japanese title', 'English title', 'Japanese description', 'English description'],
                array_column(array_column($changes, 'preview'), 'value'),
            );
        }
    }

    public function test_every_new_work_review_category_has_json_paths(): void
    {
        foreach (array_slice(ImportReview::CATEGORIES['works'], 1) as $category) {
            $this->assertNotEmpty(ImportValue::newWorkJsonPaths($category), $category);
        }
    }

    public function test_dlsite_rating_new_work_category_maps_to_all_three_detail_paths(): void
    {
        $this->assertSame([
            'incoming->details->dlsite_rating_5',
            'incoming->details->dlsite_rating_10',
            'incoming->details->dlsite_rating_count',
        ], ImportValue::newWorkJsonPaths('dlsite_rating'));
    }

    public function test_dlsite_rating_new_work_category_exposes_both_scales_and_vote_count(): void
    {
        $item = new LibraryImportItem([
            'library_transfer_run_id' => 42,
            'incoming' => [
                'details' => [
                    'dlsite_rating_5' => '4.97',
                    'dlsite_rating_10' => '9.93',
                    'dlsite_rating_count' => 1330,
                ],
            ],
        ]);

        $changes = ImportValue::newWorkChanges($item, 'dlsite_rating');

        $this->assertSame(
            ['dlsite_rating_5', 'dlsite_rating_10', 'dlsite_rating_count'],
            array_column($changes, 'key'),
        );
        $this->assertSame(['Up to 5', 'Up to 10', 'Vote count'], array_column($changes, 'label'));
        $this->assertSame(1330, $changes[2]['preview']['value']);
        $this->assertSame('DLsite Rating', ImportValue::label('dlsite_rating'));
    }

    public function test_vote_counts_remain_numeric_in_persisted_previews(): void
    {
        foreach ([1330, 1, 0, null] as $count) {
            $preview = ImportValue::previewValue(
                ['dlsite_rating_5' => '4.97', 'dlsite_rating_count' => $count],
                'dlsite_rating',
                'dlsite_rating',
                42,
            );

            $this->assertSame($count, $preview['value']['dlsite_rating_count']);
        }
    }
}
