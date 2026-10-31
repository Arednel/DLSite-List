<?php

namespace Tests\Unit\Support\DLSite;

use App\Enums\ProductContributorRole;
use App\Support\DLSite\DLSiteWorkData;
use Tests\TestCase;

class DLSiteWorkDataTest extends TestCase
{
    public function test_it_extracts_product_metadata_and_collapses_duplicate_english_values(): void
    {
        $data = DLSiteWorkData::fromArray([
            'japanese' => [
                'product_id' => 'RJ123456',
                'site_id' => 'maniax',
                'maker_id' => 'RG123',
                'work_name' => 'JP Title',
                'age_category' => ['_name_' => 'R18'],
                'product_format' => ['MOV', 'SND', 'MS2'],
                'announce_date' => '2026-09-24 00:00:00',
                'circle' => 'Circle Name',
                'scenario' => ['Writer', 'writer'],
                'voice_actor' => ['Voice One', 'Voice Two'],
                'illustration' => ['Artist'],
                'author' => [],
                'genre' => ['JP Tag'],
                'description' => 'Same Description',
                'work_image' => 'cover.jpg',
                'sample_images' => ['sample_1.jpg', 'sample_2.jpg'],
                'title_name' => 'Series Name',
            ],
            'english' => [
                'work_name' => 'JP Title',
                'genre' => ['EN Tag'],
                'description' => 'Same Description',
            ],
        ]);

        $this->assertSame('RJ123456', $data->productId);
        $this->assertSame('maniax', $data->siteId);
        $this->assertSame('RG123', $data->makerId);
        $this->assertSame('JP Title', $data->workName);
        $this->assertNull($data->englishWorkName);
        $this->assertSame('R18', $data->ageCategory);
        $this->assertSame(['MOV', 'SND', 'MS2'], $data->productFormat);
        $this->assertSame('2026-09-24 00:00:00', $data->announceDate);
        $this->assertSame('Circle Name', $data->circle);
        $this->assertNull($data->englishDescription);
        $this->assertSame('Series Name', $data->autoSeries());
        $this->assertSame('cover.jpg', $data->workImage);
        $this->assertSame(['sample_1.jpg', 'sample_2.jpg'], $data->sampleImages);
        $this->assertSame(['Writer'], $data->contributorsByRole[ProductContributorRole::Scenario->value]);
        $this->assertSame(['Voice One', 'Voice Two'], $data->contributorsByRole[ProductContributorRole::VoiceActor->value]);
        $this->assertSame(['Circle Name'], $data->contributorsByRole[ProductContributorRole::Circle->value]);
    }

    public function test_circle_publisher_and_brand_are_independent_and_use_english_fallback(): void
    {
        $work = DLSiteWorkData::fromArray([
            'japanese' => [
                'product_id' => 'BJ370220',
                'circle' => 'Circle JP',
                'publisher' => '',
                'brand' => 'Brand JP',
            ],
            'english' => [
                'circle' => 'Circle EN',
                'publisher' => 'Publisher EN',
                'brand' => 'Brand EN',
            ],
        ]);

        $this->assertSame('Circle JP', $work->circle);
        $this->assertSame('Publisher EN', $work->publisher);
        $this->assertSame('Brand JP', $work->brand);
        $this->assertSame(['Circle JP'], $work->contributorsByRole[ProductContributorRole::Circle->value]);

        $onlyPublisher = DLSiteWorkData::fromArray([
            'japanese' => ['product_id' => 'BJ370220', 'publisher' => 'KADOKAWA'],
        ]);
        $this->assertNull($onlyPublisher->circle);
        $this->assertSame('KADOKAWA', $onlyPublisher->publisher);
        $this->assertNull($onlyPublisher->brand);
        $this->assertSame([], $onlyPublisher->contributorsByRole[ProductContributorRole::Circle->value]);
    }

    public function test_empty_japanese_circle_uses_english_fallback_for_property_and_contributor(): void
    {
        foreach (['', null] as $japaneseCircle) {
            $work = DLSiteWorkData::fromArray([
                'japanese' => ['product_id' => 'RJ123456', 'circle' => $japaneseCircle],
                'english' => ['circle' => 'English Circle'],
            ]);

            $this->assertSame('English Circle', $work->circle);
            $this->assertSame(['English Circle'], $work->contributorsByRole[ProductContributorRole::Circle->value]);
        }
    }

    public function test_product_format_uses_english_fallback_and_preserves_order_while_deduplicating(): void
    {
        $data = DLSiteWorkData::fromArray([
            'japanese' => [
                'product_id' => 'RJ123456',
                'product_format' => [],
            ],
            'english' => [
                'product_format' => ['MOV', 'MV2', 'SND', 'MOV', 'MS2', 'custom:Audiobook'],
            ],
        ]);

        $this->assertSame(['MOV', 'MV2', 'SND', 'MS2', 'custom:Audiobook'], $data->productFormat);
    }

    public function test_announcement_date_uses_english_fallback(): void
    {
        $data = DLSiteWorkData::fromArray([
            'japanese' => ['product_id' => 'RJ123456', 'announce_date' => null],
            'english' => ['announce_date' => '2026-09-24 00:00:00'],
        ]);

        $this->assertSame('2026-09-24 00:00:00', $data->announceDate);
        $this->assertNull(DLSiteWorkData::fromArray([
            'japanese' => ['product_id' => 'RJ123456', 'announce_date' => null],
        ])->announceDate);
    }

    public function test_site_id_uses_english_fallback_and_remains_null_when_missing(): void
    {
        $data = DLSiteWorkData::fromArray([
            'japanese' => ['product_id' => 'BJ370220', 'site_id' => ''],
            'english' => ['site_id' => 'comic'],
        ]);

        $this->assertSame('comic', $data->siteId);
        $this->assertNull(DLSiteWorkData::fromArray([
            'japanese' => ['product_id' => 'RJ123456'],
        ])->siteId);
    }

    public function test_it_keeps_distinct_english_description(): void
    {
        $data = DLSiteWorkData::fromArray([
            'japanese' => [
                'product_id' => 'RJ123456',
                'description' => 'Japanese Description',
            ],
            'english' => [
                'description' => 'English Description',
            ],
        ]);

        $this->assertSame('Japanese Description', $data->description);
        $this->assertSame('English Description', $data->englishDescription);
    }

    public function test_it_uses_fallback_product_id_when_json_does_not_include_one(): void
    {
        $data = DLSiteWorkData::fromArray([
            'japanese' => [
                'work_name' => 'Fallback Title',
            ],
        ], 'RJ999999');

        $this->assertSame('RJ999999', $data->productId);
        $this->assertSame('Fallback Title', $data->workName);
    }

    public function test_it_throws_when_product_id_and_fallback_are_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DLsite work data is missing a product id.');

        DLSiteWorkData::fromArray([
            'japanese' => [
                'work_name' => 'Missing Product Id',
            ],
        ]);
    }

    public function test_new_api_maps_maker_roles_and_accepts_translation_provider_identity(): void
    {
        foreach (['RG60289' => 'circle', 'BG01675' => 'publisher', 'VG03026' => 'brand'] as $makerId => $role) {
            $work = DLSiteWorkData::fromArray(['japanese' => [
                'product_id' => 'RJ01689827',
                'maker_id' => $makerId,
                'maker_name' => 'みんなで翻訳',
            ]]);

            foreach (['circle', 'publisher', 'brand'] as $field) {
                $this->assertSame($field === $role ? 'みんなで翻訳' : null, $work->{$field});
            }
            $this->assertSame($role === 'circle' ? ['みんなで翻訳'] : [], $work->contributorsByRole['circle']);
        }
    }

    public function test_new_api_maps_description_and_creater_to_existing_fields(): void
    {
        $work = DLSiteWorkData::fromArray([
            'japanese' => [
                'product_id' => 'RJ01724792',
                'intro_masked' => null,
                'intro_s' => '【狂気的な愛】×4を体験してみない?',
                'intro' => 'Unused description',
                'author' => null,
                'creater' => ['タツタアゲ学長'],
            ],
        ]);

        $this->assertSame('【狂気的な愛】×4を体験してみない?', $work->description);
        $this->assertSame(['タツタアゲ学長'], $work->contributorsByRole['author']);
    }

    public function test_author_fallback_preserves_missing_null_empty_and_populated_values(): void
    {
        foreach (
            [
                [['creater' => ['Creator']], ['Creator']],
                [['author' => null, 'creater' => ['Creator']], ['Creator']],
                [['author' => [], 'creater' => ['Creator']], []],
                [['author' => ['Author'], 'creater' => ['Creator']], ['Author']],
            ] as [$locale, $expected]
        ) {
            $work = DLSiteWorkData::fromArray(['japanese' => ['product_id' => 'RJ123456', ...$locale]]);
            $this->assertSame($expected, $work->contributorsByRole['author']);
        }
    }

    public function test_explicit_legacy_fields_win_over_new_aliases(): void
    {
        $document = ['japanese' => [
            'product_id' => 'RJ123456',
            'maker_id' => 'RG123',
            'maker_name' => 'New maker',
            'circle' => null,
            'description' => null,
            'intro_s' => 'New description',
            'author' => [],
            'creater' => ['New creator'],
        ]];

        $normalized = DLSiteWorkData::normalizePayload($document);
        $this->assertSame($normalized, DLSiteWorkData::normalizePayload($normalized));

        $work = DLSiteWorkData::fromArray($normalized);
        $this->assertNull($work->circle);
        $this->assertNull($work->description);
        $this->assertSame([], $work->contributorsByRole['author']);
    }

    public function test_original_work_id_uses_japanese_language_editions_then_translation_info(): void
    {
        $this->assertSame('RJ01463781', DLSiteWorkData::originalWorkId(['japanese' => [
            'language_editions' => [
                ['workno' => 'RJ01463786', 'display_order' => 3],
                ['workno' => 'RJ01463781', 'display_order' => 1],
            ],
            'translation_info' => ['original_workno' => 'RJ999999'],
        ]]));

        $this->assertSame('RJ01647180', DLSiteWorkData::originalWorkId(['japanese' => [
            'translation_info' => ['original_workno' => 'RJ01647180'],
        ]]));

        $this->assertSame('RJ01647180', DLSiteWorkData::originalWorkId(['japanese' => [
            'language_editions' => [
                ['workno' => '../bad', 'display_order' => 1],
            ],
            'translation_info' => ['original_workno' => 'RJ01647180'],
        ]]));

        $this->assertNull(DLSiteWorkData::originalWorkId(['japanese' => []]));
    }

    public function test_original_maker_overrides_circle_and_id_without_changing_translated_metadata(): void
    {
        $translated = ['japanese' => [
            'product_id' => 'RJ01689827',
            'maker_id' => 'RG60289',
            'maker_name' => 'Translation provider',
            'circle' => 'Explicit translation circle',
            'work_name' => 'Translated title',
            'description' => 'Translated description',
            'publisher' => 'Publisher',
            'brand' => 'Brand',
            'scenario' => ['Translated credit'],
        ]];
        $original = ['japanese' => ['maker_id' => 'RG49556', 'maker_name' => 'RaRo', 'work_name' => 'Original title']];

        $work = DLSiteWorkData::fromArray($translated, originalWorkData: $original);
        $this->assertSame('RaRo', $work->circle);
        $this->assertSame('RG49556', $work->makerId);
        $this->assertSame(['RaRo'], $work->contributorsByRole['circle']);
        $this->assertSame('Explicit translation circle', $translated['japanese']['circle']);
        $this->assertSame('RJ01689827', $work->productId);
        $this->assertSame('Translated title', $work->workName);
        $this->assertSame('Translated description', $work->description);
        $this->assertSame('Publisher', $work->publisher);
        $this->assertSame('Brand', $work->brand);
        $this->assertSame(['Translated credit'], $work->contributorsByRole['scenario']);
    }

    public function test_original_maker_uses_a_complete_pair_from_one_locale(): void
    {
        $original = [
            'japanese' => ['maker_id' => 'RG111', 'maker_name' => ''],
            'english' => ['maker_id' => 'RG222', 'maker_name' => 'English circle'],
        ];
        $this->assertSame(['maker_id' => 'RG222', 'maker_name' => 'English circle'], DLSiteWorkData::originalMaker($original));
        $original['japanese']['maker_name'] = 'Japanese circle';
        $this->assertSame(['maker_id' => 'RG111', 'maker_name' => 'Japanese circle'], DLSiteWorkData::originalMaker($original));

        foreach ([[], ['maker_id' => 'BG111', 'maker_name' => 'Publisher'], ['maker_id' => 'RG111'], ['maker_name' => 'Circle']] as $locale) {
            $work = DLSiteWorkData::fromArray(['japanese' => [
                'product_id' => 'RJ01689827',
                'maker_id' => 'RG60289',
                'circle' => 'Provider',
            ]], originalWorkData: ['japanese' => $locale]);
            $this->assertSame('Provider', $work->circle);
            $this->assertSame('RG60289', $work->makerId);
        }
    }

    public function test_new_api_announcement_date_only_strips_japan_offset(): void
    {
        $this->assertSame('2026-05-27 00:00:00', DLSiteWorkData::fromArray(['japanese' => [
            'product_id' => 'RJ01637254',
            'announce_date' => '2026-05-27 00:00:00+09:00',
        ]])->announceDate);

        $this->assertSame('2026-05-27 00:00:00', DLSiteWorkData::fromArray(['japanese' => [
            'product_id' => 'RJ01637254',
            'announce_date' => '2026-05-27 00:00:00',
        ]])->announceDate);

        $this->assertSame('2026-05-26 15:00:00+00:00', DLSiteWorkData::fromArray(['japanese' => [
            'product_id' => 'RJ01637254',
            'announce_date' => '2026-05-26 15:00:00+00:00',
        ]])->announceDate);

        $this->assertSame('2026-02-30 00:00:00', DLSiteWorkData::fromArray(['japanese' => [
            'product_id' => 'RJ01637254',
            'announce_date' => '2026-02-30 00:00:00+09:00',
        ]])->announceDate);

        $this->assertNull(DLSiteWorkData::fromArray(['japanese' => [
            'product_id' => 'RJ01722318',
            'regist_date' => '2026-10-14 00:00:00+09:00',
        ]])->announceDate);
    }
}
