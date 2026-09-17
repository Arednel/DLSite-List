<?php

namespace Tests\Feature;

use App\Enums\LibraryImportItemStatus;
use App\Models\Option;
use App\Models\Product;
use App\Support\Transfers\ImportReview;
use App\Support\Transfers\LibraryData;
use App\Support\Transfers\LibraryTransferService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibraryTransferConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_locking_read_detects_a_committed_edit_even_with_an_older_mysql_snapshot(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This regression requires MySQL REPEATABLE READ.');
        }
        Bus::fake();
        Storage::fake('local');
        Storage::fake('public');
        config(['queue.default' => 'database', 'database.connections.transfer_concurrent' => config('database.connections.'.config('database.default'))]);
        $product = Product::factory()->create(['id' => 'RJ123456', 'work_name' => 'Original']);
        $run = app(LibraryTransferService::class)->startImport();
        $review = app(ImportReview::class);
        $record = app(LibraryData::class)->work($product);
        $record['titles']['work_name'] = 'Imported';
        DB::beginTransaction();
        try {
            $review->stage($run, 'works', $record, []);
            $item = $run->items()->where('metadata->field', 'work_name')->firstOrFail();
            DB::connection('transfer_concurrent')->table('products')->where('id', $product->id)->update(['work_name' => 'Concurrent local edit']);
            // A normal read still sees the old snapshot; a locking read must see the committed edit.
            $this->assertSame('Original', Product::find($product->id)->work_name);
            $item->update(['decision' => 'overwrite']);
            $review->applyItem($item);
            $this->assertSame(LibraryImportItemStatus::Conflict, $item->fresh()->status);
            DB::commit();
            $this->assertSame('Concurrent local edit', $product->fresh()->work_name);
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('transfer_concurrent');
        }
    }

    public function test_option_conflicts_use_the_locking_value_not_an_older_mysql_snapshot(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('This regression requires MySQL REPEATABLE READ.');
        }
        Bus::fake();
        Storage::fake('local');
        config(['queue.default' => 'database', 'database.connections.transfer_concurrent' => config('database.connections.'.config('database.default'))]);
        Option::setIndexPerPage(25);
        $run = app(LibraryTransferService::class)->startImport();
        $review = app(ImportReview::class);
        DB::beginTransaction();
        try {
            $review->stage($run, 'options', ['key' => Option::INDEX_PER_PAGE, 'value' => 100], []);
            $item = $run->items()->firstOrFail();
            DB::connection('transfer_concurrent')->table('options')->where('key', Option::INDEX_PER_PAGE)->update(['value' => '50']);
            $this->assertSame(25, Option::indexPerPage());
            $item->update(['decision' => 'overwrite']);
            $review->applyItem($item);
            $this->assertSame(LibraryImportItemStatus::Conflict, $item->fresh()->status);
            DB::commit();
            $this->assertSame(50, Option::indexPerPage());
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('transfer_concurrent');
        }
    }
}
