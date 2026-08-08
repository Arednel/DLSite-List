<?php

namespace Tests\Unit\Support;

use App\Enums\ProductContributorRole;
use App\Enums\RefetchCategory;
use App\Models\Genre;
use App\Models\Product;
use App\Support\DLSite\DLSiteFetchResult;
use App\Support\DLSite\DLSiteWorkData;
use App\Support\ProductContributorSync;
use App\Support\ProductGenreSync;
use App\Support\Refetch\RefetchDiffBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RefetchDiffBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_full_metadata_diffs_and_keeps_failed_sample_images_unavailable(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create([
            'maker_id' => 'RGOLD',
            'work_name' => 'Old JP',
            'work_name_english' => 'Old EN',
            'age_category' => 'ALL_AGES',
            'circle' => 'Old Circle',
            'series' => 'Old Series',
            'description' => 'Old Description',
            'description_english' => 'Old English Description',
            'sample_images' => ['storage/Works/RJ123456/sample_1.jpg'],
        ]);
        app(ProductContributorSync::class)->sync($product, [
            ProductContributorRole::Circle->value => ['Old Circle'],
            ProductContributorRole::Scenario->value => ['Old Writer'],
        ], 'RGOLD');
        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_JAPANESE => Genre::resolveIdsFromTitles(['Old JP Tag']),
            Genre::LANGUAGE_ENGLISH => Genre::resolveIdsFromTitles(['Old EN Tag']),
        ], Genre::resolveIdsFromTitles(['Custom Tag']));

        Storage::disk('public')->put("Works/{$product->id}/cover.jpg", 'old-cover');
        Storage::disk('public')->put('Refetch/1/Works/' . $product->id . '/cover.jpg', 'new-cover');
        Storage::disk('public')->put('Refetch/1/Works/' . $product->id . '/sample_1.jpg', 'new-sample');

        $raw = [
            'japanese' => [
                'product_id' => $product->id,
                'maker_id' => 'RGNEW',
                'work_name' => 'New JP',
                'age_category' => ['_name_' => 'R18'],
                'circle' => 'New Circle',
                'scenario' => ['New Writer'],
                'voice_actor' => ['New Voice'],
                'illustration' => ['New Artist'],
                'author' => ['New Author'],
                'genre' => ['New JP Tag'],
                'description' => 'New Description',
                'title_name' => 'New Series',
                'work_image' => 'cover-url',
                'sample_images' => ['sample-1', 'sample-2'],
            ],
            'english' => [
                'work_name' => 'New EN',
                'genre' => ['New EN Tag'],
                'description' => 'New English Description',
            ],
        ];
        $workData = DLSiteWorkData::fromArray($raw);
        $fetch = new DLSiteFetchResult(
            workData: $workData,
            failedImages: ['sample_2.jpg'],
        );

        $changes = app(RefetchDiffBuilder::class)->build(
            $product,
            $fetch,
            'Refetch/1/Works/' . $product->id,
        );

        foreach (
            [
                RefetchCategory::Titles,
                RefetchCategory::Descriptions,
                RefetchCategory::Series,
                RefetchCategory::Age,
                RefetchCategory::Circle,
                RefetchCategory::Maker,
                RefetchCategory::Scenario,
                RefetchCategory::VoiceActor,
                RefetchCategory::Illustration,
                RefetchCategory::Author,
                RefetchCategory::Tags,
                RefetchCategory::Cover,
            ] as $category
        ) {
            $this->assertArrayHasKey($category->value, $changes);
        }

        $this->assertArrayNotHasKey(RefetchCategory::SampleImages->value, $changes);
        $this->assertSame('Custom Tag', $changes['tags']['tags']['old']['custom'][0]);
    }

    public function test_materially_renamed_fetched_tag_is_distinct_from_refetched_original_name(): void
    {
        $product = Product::factory()->create();
        $tag = Genre::resolveByTitle('Original DLSite Tag');
        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_JAPANESE => [$tag->getKey()],
        ], []);
        $tag->forceFill(['title' => 'Manually Renamed Tag'])->save();

        $changes = app(RefetchDiffBuilder::class)->build(
            $product,
            $this->fetchWithJapaneseTags($product, ['Original DLSite Tag']),
            null,
        );
        $tagChange = $changes['tags']['tags'];

        $this->assertSame(['Manually Renamed Tag'], $tagChange['old']['japanese']);
        $this->assertSame(['Original DLSite Tag'], $tagChange['new']['japanese']);
        $this->assertSame(['Manually Renamed Tag'], $tagChange['details']['stale_japanese']);
        $this->assertSame(['Original DLSite Tag'], $tagChange['details']['added_japanese']);
        $this->assertFalse($tag->is(Genre::resolveByTitle('Original DLSite Tag')));
    }

    public function test_case_only_renamed_fetched_tag_remains_the_same_during_refetch(): void
    {
        $product = Product::factory()->create();
        $tag = Genre::resolveByTitle('ASMR');
        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_JAPANESE => [$tag->getKey()],
        ], []);
        $tag->forceFill(['title' => 'Asmr'])->save();

        $changes = app(RefetchDiffBuilder::class)->build(
            $product,
            $this->fetchWithJapaneseTags($product, ['ASMR']),
            null,
        );
        $resolved = Genre::resolveByTitle('ASMR');

        $this->assertArrayNotHasKey('tags', $changes);
        $this->assertTrue($tag->is($resolved));
        $this->assertSame('Asmr', $resolved->title);
    }

    /**
     * @param  list<string>  $tags
     */
    private function fetchWithJapaneseTags(Product $product, array $tags): DLSiteFetchResult
    {
        return new DLSiteFetchResult(
            workData: DLSiteWorkData::fromArray([
                'japanese' => [
                    'product_id' => $product->getKey(),
                    'genre' => $tags,
                ],
            ]),
            failedImages: [],
        );
    }
}
