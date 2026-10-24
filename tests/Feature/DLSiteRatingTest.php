<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RefetchWorkResult;
use App\Support\Refetch\RefetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DLSiteRatingTest extends TestCase
{
    use RefreshDatabase;

    private const WORK = 'RJ01741555';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Process::fake(['*' => Process::result(output: '{"failed_images":[]}')])->preventStrayProcesses();
    }

    public function test_import_stores_the_dlsite_rating(): void
    {
        $this->putScrapedWork(self::WORK, ['rate_average_2dp' => 4.96, 'rate_count' => 57]);

        $this->post('/store', ['id' => self::WORK])->assertSessionHasNoErrors();

        $product = Product::findOrFail(self::WORK);
        $this->assertSame(4.96, $product->dlsite_rating);
        $this->assertSame(57, $product->dlsite_rating_count);
    }

    public function test_import_stores_no_rating_for_unrated_or_unreadable_works(): void
    {
        $this->putScrapedWork(self::WORK, ['rate_average_2dp' => null, 'rate_count' => null]);
        $this->post('/store', ['id' => self::WORK])->assertSessionHasNoErrors();

        // The rating lookup failed, so the scraper left the keys out.
        $this->putScrapedWork('RJ01722318', null);
        $this->post('/store', ['id' => 'RJ01722318'])->assertSessionHasNoErrors();

        foreach ([self::WORK, 'RJ01722318'] as $id) {
            $product = Product::findOrFail($id);
            $this->assertNull($product->dlsite_rating, $id);
            $this->assertNull($product->dlsite_rating_count, $id);
        }
    }

    public function test_refetch_updates_the_rating_without_review_or_touching_the_update_time(): void
    {
        $updatedAt = Carbon::parse('2026-01-02 03:04:05');
        $product = Product::factory()->create([
            'id' => self::WORK,
            'work_name' => 'Title ' . self::WORK,
            'dlsite_rating' => 4.5,
            'dlsite_rating_count' => 10,
        ]);
        Product::query()->whereKey(self::WORK)->update(['updated_at' => $updatedAt]);

        $result = $this->refetch($product, ['rate_average_2dp' => 4.96, 'rate_count' => 57]);

        $product->refresh();
        $this->assertSame(4.96, $product->dlsite_rating);
        $this->assertSame(57, $product->dlsite_rating_count);
        $this->assertTrue($product->updated_at->equalTo($updatedAt));
        $this->assertTrue($result->isFetched());
        $this->assertStringNotContainsString('rate_', json_encode($result->changes, JSON_THROW_ON_ERROR));
    }

    public function test_refetch_keeps_the_rating_when_it_could_not_be_read_and_clears_it_when_dlsite_has_none(): void
    {
        $product = Product::factory()->create([
            'id' => self::WORK,
            'dlsite_rating' => 4.5,
            'dlsite_rating_count' => 10,
        ]);

        $this->refetch($product, null);
        $product->refresh();
        $this->assertSame(4.5, $product->dlsite_rating);
        $this->assertSame(10, $product->dlsite_rating_count);

        $this->refetch($product, ['rate_average_2dp' => null, 'rate_count' => null]);
        $product->refresh();
        $this->assertNull($product->dlsite_rating);
        $this->assertNull($product->dlsite_rating_count);
    }

    public function test_edit_page_shows_the_rating_read_only_and_hides_it_without_data(): void
    {
        $rated = Product::factory()->create([
            'dlsite_rating' => 4.5,
            'dlsite_rating_count' => 1234,
        ]);
        $unrated = Product::factory()->create();

        $this->get("/edit/{$rated->id}")
            ->assertOk()
            ->assertSee('dlsite-rating-row', false)
            ->assertSee('DLsite rating')
            ->assertSee('★4.50 (1,234 ratings)')
            ->assertDontSee('name="dlsite_rating', false);

        $this->get("/edit/{$unrated->id}")
            ->assertOk()
            ->assertDontSee('dlsite-rating-row', false);
    }

    public function test_edit_page_rating_is_localized(): void
    {
        $product = Product::factory()->create([
            'dlsite_rating' => 4.96,
            'dlsite_rating_count' => 1,
        ]);

        $this->get("/edit/{$product->id}")->assertSee('★4.96 (1 rating)');

        app()->setLocale('zh_TW');
        $this->assertSame('★4.96（57 人）', trans_choice('★:rating (:count rating)|★:rating (:count ratings)', 57, ['rating' => '4.96', 'count' => '57']));
        $this->assertSame('DLsite 評價', __('DLsite rating'));

        app()->setLocale('ja');
        $this->assertSame('★4.96（57件）', trans_choice('★:rating (:count rating)|★:rating (:count ratings)', 57, ['rating' => '4.96', 'count' => '57']));
    }

    /** @param  array<string, mixed>|null  $rating  Raw scraper rating keys; null leaves them out. */
    private function putScrapedWork(string $workId, ?array $rating): void
    {
        Storage::disk('local')->put("Works/{$workId}.json", json_encode(
            $this->scrapedWork($workId, $rating),
            JSON_THROW_ON_ERROR,
        ));
    }

    private function scrapedWork(string $workId, ?array $rating): array
    {
        $japanese = [
            'product_id' => $workId,
            'maker_id' => 'RG12345',
            'work_name' => "Title {$workId}",
            'age_category' => ['_name_' => 'ALL_AGES'],
            'circle' => 'Circle',
            'sample_images' => [],
            'genre' => [],
            ...($rating ?? []),
        ];

        return ['japanese' => $japanese, 'english' => ['work_name' => "English {$workId}", 'genre' => []]];
    }

    private function refetch(Product $product, ?array $rating): RefetchWorkResult
    {
        $service = app(RefetchService::class);
        $run = $service->createRun([$product->id], false);
        Storage::disk('local')->put(
            "Refetch/{$run->id}/Works/{$product->id}.json",
            json_encode($this->scrapedWork($product->id, $rating), JSON_THROW_ON_ERROR),
        );
        $result = $run->results()->firstOrFail();

        $service->fetchAndRecordResult($result);

        return $result->refresh();
    }
}
