<?php

namespace Tests\Unit\Support\Transfers;

use App\Enums\DlsiteLanguage;
use App\Enums\UiLanguage;
use App\Models\LibraryImportItem;
use App\Support\Transfers\ImportReview;
use App\Support\Transfers\ImportValue;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Tests\LanguageCases;
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

    #[DataProviderExternal(LanguageCases::class, 'ui')]
    public function test_localized_metadata_labels_follow_the_current_ui_catalog(UiLanguage $ui): void
    {
        app()->setLocale($ui->value);
        foreach (DlsiteLanguage::cases() as $language) {
            $code = $language->value;
            $title = $language === DlsiteLanguage::Japanese ? 'work_name' : "localization:{$code}:title";
            $description = $language === DlsiteLanguage::Japanese ? 'description' : "localization:{$code}:description";
            $this->assertSame($language->label(), ImportValue::label($code));
            $this->assertSame($language->titleLabel(), ImportValue::label($title));
            $this->assertSame($language->descriptionLabel(), ImportValue::label($description));
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

    #[DataProviderExternal(LanguageCases::class, 'ui')]
    public function test_new_work_previews_label_every_metadata_language_and_preserve_its_text(UiLanguage $ui): void
    {
        app()->setLocale($ui->value);
        $incoming = [];
        foreach (DlsiteLanguage::cases() as $language) {
            $incoming['titles'][$language->value] = "Title [{$language->value}]";
            $incoming['descriptions'][$language->value] = "Description [{$language->value}]";
        }
        $item = new LibraryImportItem(['library_transfer_run_id' => 42, 'incoming' => $incoming]);

        foreach ($incoming as $category => $values) {
            $changes = ImportValue::newWorkChanges($item, $category);
            $this->assertSame(array_keys($values), array_column($changes, 'key'));
            foreach ($changes as $change) {
                $code = $change['key'];
                $language = DlsiteLanguage::from($code);
                $label = $category === 'titles' ? $language->titleLabel() : $language->descriptionLabel();
                $this->assertSame($label, $change['label']);
                $this->assertSame($values[$code], $change['preview']['value']);
            }
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
