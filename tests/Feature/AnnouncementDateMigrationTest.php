<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AnnouncementDateMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_imports_saved_dates_and_skips_missing_or_corrupt_json(): void
    {
        Storage::fake('local');

        foreach (['RJ000000701', 'RJ000000702', 'RJ000000703', 'RJ000000704'] as $id) {
            Product::factory()->create(['id' => $id]);
        }
        Storage::disk('local')->put('Works/RJ000000701.json', json_encode([
            'japanese' => ['product_id' => 'RJ000000701', 'announce_date' => '2026-09-24 00:00:00'],
            'english' => [],
        ], JSON_THROW_ON_ERROR));
        Storage::disk('local')->put('Works/RJ000000702.json', json_encode([
            'japanese' => ['product_id' => 'RJ000000702'],
            'english' => ['announce_date' => '2026-09-25 12:30:00'],
        ], JSON_THROW_ON_ERROR));
        // 703 has no saved scraper JSON, while 704 has an unreadable one.
        Storage::disk('local')->put('Works/RJ000000704.json', '{invalid json');

        $this->removeCurrentAnnouncementDateColumn();
        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('products', 'announce_date'));
        $this->assertSame('2026-09-24 00:00:00', Product::findOrFail('RJ000000701')->announce_date?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-25 12:30:00', Product::findOrFail('RJ000000702')->announce_date?->format('Y-m-d H:i:s'));
        $this->assertNull(Product::findOrFail('RJ000000703')->announce_date);
        $this->assertNull(Product::findOrFail('RJ000000704')->announce_date);
    }

    public function test_backfill_database_update_error_escapes_instead_of_being_logged_and_skipped(): void
    {
        Storage::fake('local');
        Product::factory()->create(['id' => 'RJ000000705']);
        Storage::disk('local')->put('Works/RJ000000705.json', json_encode([
            'japanese' => ['product_id' => 'RJ000000705', 'announce_date' => '2026-09-24 00:00:00'],
            'english' => [],
        ], JSON_THROW_ON_ERROR));

        $this->removeCurrentAnnouncementDateColumn();

        // An isolated query-event dispatcher simulates a failure while updating the announcement date. Restore it so other tests are not affected.
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $testDispatcher = clone $originalDispatcher;
        $testDispatcher->listen(QueryExecuted::class, static function (QueryExecuted $query): void {
            if (
                str_starts_with(strtolower(ltrim($query->sql)), 'update')
                && str_contains($query->sql, 'announce_date')
            ) {
                throw new RuntimeException('Simulated announcement-date database update failure.');
            }
        });
        $connection->setEventDispatcher($testDispatcher);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Simulated announcement-date database update failure.');
            $this->migration()->up();
        } finally {
            $connection->setEventDispatcher($originalDispatcher);
        }
    }

    private function removeCurrentAnnouncementDateColumn(): void
    {
        // RefreshDatabase already migrated the current schema. Recreate the pre-change products layout to exercise the actual historical migration's up() method.
        Schema::table('products', static function (Blueprint $table): void {
            $table->dropColumn('announce_date');
        });
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_01_000000_add_announce_date_to_products_table.php');
    }
}
