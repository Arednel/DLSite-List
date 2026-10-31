<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\Transfers\LibraryData;
use Tests\Feature\Concerns\InteractsWithLibraryTransfers;
use Tests\TestCase;

class DLSiteRatingTransferTest extends TestCase
{
    use InteractsWithLibraryTransfers;

    public function test_rating_round_trips_through_export_and_import(): void
    {
        Product::factory()->create(['id' => 'RJ01741555', 'dlsite_rating' => 5.0, 'dlsite_rating_count' => 105]);
        Product::factory()->create(['id' => 'RJ01000001']);
        $export = $this->exported();
        Product::query()->delete();

        $this->apply($this->imported($export));

        $rated = Product::findOrFail('RJ01741555');
        $this->assertSame(5.0, $rated->dlsite_rating);
        $this->assertSame(105, $rated->dlsite_rating_count);
        $unrated = Product::findOrFail('RJ01000001');
        $this->assertNull($unrated->dlsite_rating);
        $this->assertNull($unrated->dlsite_rating_count);
    }

    public function test_import_reviews_a_changed_rating_for_an_existing_work(): void
    {
        $product = Product::factory()->create(['id' => 'RJ01741555', 'dlsite_rating' => 4.96, 'dlsite_rating_count' => 57]);
        $export = $this->exported();
        $product->update(['dlsite_rating' => 4.5, 'dlsite_rating_count' => 60]);

        $run = $this->imported($export);
        $this->assertSame('pending', $run->items()->where('category', 'dlsite_rating')->firstOrFail()->status->value);
        $this->assertSame('pending', $run->items()->where('category', 'dlsite_rating_count')->firstOrFail()->status->value);

        $this->apply($run);
        $product->refresh();
        $this->assertSame(4.96, $product->dlsite_rating);
        $this->assertSame(57, $product->dlsite_rating_count);
    }

    public function test_unchanged_whole_number_rating_matches_the_stored_value(): void
    {
        Product::factory()->create(['id' => 'RJ01741555', 'dlsite_rating' => 5.0, 'dlsite_rating_count' => 105]);
        $export = $this->exported();
        $this->rewriteWorkData($export->parts()->firstOrFail(), function (array $work): array {
            // DLsite and hand-edited archives may write 5 instead of 5.0.
            $work['japanese']['rate_average_2dp'] = 5;
            $work['english']['rate_average_2dp'] = 5;

            return $work;
        });

        $run = $this->imported($export);

        $items = $run->items()->whereIn('category', ['dlsite_rating', 'dlsite_rating_count'])->get();
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame(LibraryData::hash($item->baseline), LibraryData::hash($item->incoming), $item->category);
        }
    }

    public function test_archive_from_before_ratings_keeps_the_stored_rating(): void
    {
        Product::factory()->create(['id' => 'RJ01741555', 'dlsite_rating' => 4.96, 'dlsite_rating_count' => 57]);
        $export = $this->exported();
        $this->rewriteWorkData($export->parts()->firstOrFail(), function (array $work): array {
            unset(
                $work['japanese']['rate_average_2dp'],
                $work['japanese']['rate_count'],
                $work['english']['rate_average_2dp'],
                $work['english']['rate_count'],
            );
            $work['japanese']['work_name'] = 'Imported legacy title';

            return $work;
        });

        $run = $this->imported($export);
        $this->assertFalse($run->items()->whereIn('category', ['dlsite_rating', 'dlsite_rating_count'])->exists());
        $this->apply($run);

        $product = Product::findOrFail('RJ01741555');
        $this->assertSame('Imported legacy title', $product->work_name);
        $this->assertSame(4.96, $product->dlsite_rating);
        $this->assertSame(57, $product->dlsite_rating_count);
    }

    public function test_invalid_rating_is_quarantined(): void
    {
        Product::factory()->create(['id' => 'RJ01741555']);
        $export = $this->exported();
        $this->rewriteWorkData($export->parts()->firstOrFail(), function (array $work): array {
            $work['japanese']['rate_average_2dp'] = 7.5;

            return $work;
        });

        $run = $this->imported($export);

        $this->assertSame(1, $run->items()->where('status', 'unavailable')->count());
    }
}
