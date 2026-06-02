<?php

namespace Tests\Unit\Support\DLSite;

use App\Enums\ProductContributorRole;
use App\Support\DLSite\DLSiteWorkData;
use PHPUnit\Framework\TestCase;

class DLSiteWorkDataTest extends TestCase
{
    public function test_it_extracts_product_metadata_and_collapses_duplicate_english_values(): void
    {
        $data = DLSiteWorkData::fromArray([
            'japanese' => [
                'product_id' => 'RJ123456',
                'maker_id' => 'RG123',
                'work_name' => 'JP Title',
                'age_category' => ['_name_' => 'R18'],
                'circle' => 'Circle Name',
                'scenario' => ['Writer', 'writer'],
                'voice_actor' => ['Voice One', 'Voice Two'],
                'illustration' => ['Artist'],
                'author' => [],
                'genre' => ['JP Tag'],
                'description' => 'Same Description',
                'sample_images' => ['sample_1.jpg'],
                'title_name' => 'Series Name',
            ],
            'english' => [
                'work_name' => 'JP Title',
                'genre' => ['EN Tag'],
                'description' => 'Same Description',
            ],
        ]);

        $this->assertSame('RJ123456', $data->productId);
        $this->assertSame('RG123', $data->makerId);
        $this->assertSame('JP Title', $data->workName);
        $this->assertNull($data->englishWorkName);
        $this->assertSame('R18', $data->ageCategory);
        $this->assertSame('Circle Name', $data->circle);
        $this->assertNull($data->englishDescription);
        $this->assertSame('Series Name', $data->autoSeries());
        $this->assertSame(['Writer'], $data->contributorsByRole[ProductContributorRole::Scenario->value]);
        $this->assertSame(['Voice One', 'Voice Two'], $data->contributorsByRole[ProductContributorRole::VoiceActor->value]);
        $this->assertSame(['Circle Name'], $data->contributorsByRole[ProductContributorRole::Circle->value]);
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
        $this->expectExceptionMessage('DLSite work data is missing a product id.');

        DLSiteWorkData::fromArray([
            'japanese' => [
                'work_name' => 'Missing Product Id',
            ],
        ]);
    }
}
