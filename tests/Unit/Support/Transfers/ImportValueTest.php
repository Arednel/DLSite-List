<?php

namespace Tests\Unit\Support\Transfers;

use App\Models\LibraryImportItem;
use App\Support\Transfers\ImportReview;
use App\Support\Transfers\ImportValue;
use Tests\TestCase;

class ImportValueTest extends TestCase
{
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
