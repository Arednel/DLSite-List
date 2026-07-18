<?php

namespace Tests\Feature;

use App\Enums\UiLanguage;
use App\Models\Genre;
use App\Models\Option;
use App\Models\Product;
use App\Support\ProductGenreSync;
use App\Support\ReturnTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReturnTargetProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_product_omits_page_for_unlimited_pagination(): void
    {
        Product::factory()->create(['id' => 'RJ000000001', 'score' => 1]);
        Product::factory()->create(['id' => 'RJ000000002', 'score' => 3]);
        $product = Product::factory()->create(['id' => 'RJ000000003', 'score' => 5]);

        $target = ReturnTarget::fromRequest(Request::create('/store/custom', 'POST', [
            'return_query' => [
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
                'page' => '9',
            ],
        ]))->forProduct($product, perPage: Option::INDEX_PER_PAGE_UNLIMITED);

        $this->assertSame('/?sort_first_field=score&sort_first_direction=asc#RJ000000003', $target->toUrl());
    }

    public function test_for_product_omits_first_page_from_index_url(): void
    {
        $product = Product::factory()->create(['id' => 'RJ000000001', 'score' => 1]);
        Product::factory()->create(['id' => 'RJ000000002', 'score' => 3]);

        $target = ReturnTarget::fromRequest(Request::create('/store/custom', 'POST', [
            'return_query' => [
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
                'page' => '9',
            ],
        ]))->forProduct($product, perPage: 2);

        $this->assertSame('/?sort_first_field=score&sort_first_direction=asc#RJ000000001', $target->toUrl());
    }

    public function test_for_product_keeps_saved_page_when_target_is_already_visible_on_it(): void
    {
        Product::factory()->create(['id' => 'RJ000000001', 'score' => 1]);
        Product::factory()->create(['id' => 'RJ000000002', 'score' => 3]);
        $product = Product::factory()->create(['id' => 'RJ000000003', 'score' => 5]);
        Product::factory()->create(['id' => 'RJ000000004', 'score' => 7]);

        DB::enableQueryLog();

        $target = ReturnTarget::fromRequest(Request::create('/update/RJ000000003', 'POST', [
            'return_query' => [
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
                'page' => '2',
            ],
        ]))->forProduct($product, perPage: 2);

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame('/?sort_first_field=score&sort_first_direction=asc&page=2#RJ000000003', $target->toUrl());
        $this->assertCount(1, $queryLog);
    }

    public function test_for_product_uses_full_query_visibility_fast_path_before_group_cleanup(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ000000003',
            'progress' => 'Listening',
            'series' => 'VISIBLE_SERIES',
            'score' => 5,
        ]);

        DB::enableQueryLog();

        $target = ReturnTarget::fromRequest(Request::create('/update/RJ000000003', 'POST', [
            'return_query' => [
                'series' => 'VISIBLE_SERIES',
                'progress' => 'Listening',
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
                'page' => '9',
            ],
        ]))->forProduct($product, perPage: 2);

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame('/?series=VISIBLE_SERIES&progress=Listening&sort_first_field=score&sort_first_direction=asc#RJ000000003', $target->toUrl());
        $this->assertCount(3, $queryLog);
    }

    public function test_for_product_still_cleans_hidden_filters_when_visibility_is_marked_unchanged(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ000000001',
            'progress' => 'Listening',
            'series' => 'VISIBLE_SERIES',
        ]);

        $target = ReturnTarget::fromRequest(Request::create('/update/RJ000000001', 'POST', [
            'return_query' => [
                'series' => 'HIDDEN_SERIES',
                'progress' => 'Listening',
                'page' => '2',
            ],
        ]))->forProduct($product, perPage: 2, visibilityMayHaveChanged: false);

        $this->assertSame('/?progress=Listening#RJ000000001', $target->toUrl());
    }

    public function test_for_product_drops_multiple_hiding_filter_groups(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ000000001',
            'work_name' => 'VISIBLE_RETURN_TARGET',
            'progress' => 'Listening',
            'series' => 'VISIBLE_SERIES',
            'score' => 8,
        ]);

        $target = ReturnTarget::fromRequest(Request::create('/update/RJ000000001', 'POST', [
            'return_query' => [
                'search' => 'HIDDEN_SEARCH',
                'series' => 'HIDDEN_SERIES',
                'progress' => 'Listening',
                'score' => '1',
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
            ],
        ]))->forProduct($product, perPage: 2);

        $this->assertSame('/?progress=Listening&sort_first_field=score&sort_first_direction=asc#RJ000000001', $target->toUrl());
    }

    public function test_for_product_keeps_current_language_genre_filters_and_drops_other_language_filters(): void
    {
        $product = Product::factory()->create(['id' => 'RJ000000001']);
        $customGenre = Genre::resolveByTitle('Return Target Custom Tag');
        $englishGenre = Genre::resolveByTitle('Return Target English Tag');
        $japaneseGenre = Genre::resolveByTitle('Return Target Japanese Tag');
        $sharedGenre = Genre::resolveByTitle('Return Target Shared Tag');

        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_ENGLISH => [$englishGenre->getKey(), $sharedGenre->getKey()],
            Genre::LANGUAGE_JAPANESE => [$japaneseGenre->getKey(), $sharedGenre->getKey()],
        ], [$customGenre->getKey()]);

        $targetForGenre = function (Genre $genre) use ($product): string {
            return ReturnTarget::fromRequest(Request::create('/update/RJ000000001', 'POST', [
                'return_query' => ['genre' => (string) $genre->getKey()],
            ]))->forProduct($product, perPage: Option::INDEX_PER_PAGE_UNLIMITED)->toUrl();
        };
        $keptUrl = fn(Genre $genre): string => '/?genre=' . $genre->getKey() . '#RJ000000001';

        App::setLocale(UiLanguage::English->value);

        $this->assertSame($keptUrl($customGenre), $targetForGenre($customGenre));
        $this->assertSame($keptUrl($englishGenre), $targetForGenre($englishGenre));
        $this->assertSame($keptUrl($sharedGenre), $targetForGenre($sharedGenre));
        $this->assertSame('/#RJ000000001', $targetForGenre($japaneseGenre));

        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame($keptUrl($customGenre), $targetForGenre($customGenre));
        $this->assertSame($keptUrl($japaneseGenre), $targetForGenre($japaneseGenre));
        $this->assertSame($keptUrl($sharedGenre), $targetForGenre($sharedGenre));
        $this->assertSame('/#RJ000000001', $targetForGenre($englishGenre));
    }
}
