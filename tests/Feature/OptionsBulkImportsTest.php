<?php

namespace Tests\Feature;

use App\Enums\BulkImportItemStatus;
use App\Enums\BulkImportRunStatus;
use App\Enums\UiLanguage;
use App\Livewire\BulkImportRunCard;
use App\Livewire\OptionsBulkImports;
use App\Models\BulkImportRun;
use App\Models\Option;
use App\Support\BulkImport\BulkImportCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class OptionsBulkImportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_navigation_contains_bulk_imports_after_import_export(): void
    {
        $this->get(route('options.index', ['tab' => 'bulk-imports']))
            ->assertOk()
            ->assertSeeInOrder(['Import / Export', 'Bulk Imports'])
            ->assertSeeLivewire(OptionsBulkImports::class);
    }

    public function test_requested_bulk_import_run_is_highlighted_without_view_request_access(): void
    {
        $run = BulkImportRun::create([
            'status' => BulkImportRunStatus::Completed,
            'input_snapshot' => [],
            'total_count' => 1,
            'processed_count' => 1,
            'imported_count' => 1,
            'completed_at' => now(),
        ]);

        $this->get(route('options.index', [
            'tab' => 'bulk-imports',
            'bulk_import_run' => $run->id,
        ]))
            ->assertOk()
            ->assertSee('id="bulk-import-run-' . $run->id . '"', false);
    }

    public function test_active_run_shows_exact_current_work_progress_and_errors(): void
    {
        $run = BulkImportRun::create([
            'status' => BulkImportRunStatus::Running,
            'input_snapshot' => [],
            'total_count' => 10,
            'processed_count' => 2,
            'imported_count' => 1,
            'failed_count' => 1,
            'started_at' => now(),
        ]);
        $run->items()->create([
            'position' => 2,
            'product_id' => 'RJ000000502',
            'status' => BulkImportItemStatus::Failed,
            'error' => 'GeoBlocked DLSite work',
            'completed_at' => now(),
        ]);
        $run->items()->create([
            'position' => 3,
            'product_id' => 'RJ000000503',
            'status' => BulkImportItemStatus::Importing,
            'started_at' => now(),
        ]);

        Livewire::test(BulkImportRunCard::class, ['runId' => $run->id])
            ->assertSee('Importing RJ000000503 - 3 / 10')
            ->assertSee('Errors (1)')
            ->assertSee('<details class="review-errors">', false)
            ->assertSee('GeoBlocked DLSite work')
            ->assertSee('wire:poll.visible.2s="refreshRun"', false)
            ->assertSee('wire:key="bulk-import-run-' . $run->id . '"', false)
            ->assertSee('wire:key="bulk-import-issue-', false);
    }

    public function test_history_translates_known_errors_and_preserves_stored_warning_text(): void
    {
        $run = BulkImportRun::create([
            'status' => BulkImportRunStatus::Failed,
            'input_snapshot' => [],
            'total_count' => 4,
            'error' => 'Bulk import finalization failed.',
        ]);
        $run->items()->createMany([
            [
                'position' => 1,
                'product_id' => 'RJ000000701',
                'status' => BulkImportItemStatus::Skipped,
                'error' => 'Already in library.',
            ],
            [
                'position' => 2,
                'product_id' => 'RJ000000702',
                'status' => BulkImportItemStatus::Failed,
                'error' => 'GeoBlocked DLSite work',
            ],
            [
                'position' => 3,
                'product_id' => 'RJ000000703',
                'status' => BulkImportItemStatus::Imported,
                'warning' => 'DLSite data was fetched, but these images could not be downloaded: cover.jpg, sample_1.jpg',
            ],
            [
                'position' => 4,
                'product_id' => 'RJ000000704',
                'status' => BulkImportItemStatus::Failed,
                'error' => 'Unexpected scraper detail',
                'warning' => 'Legacy warning: <upstream detail>',
            ],
        ]);

        Option::setUiLanguage(UiLanguage::Japanese);
        $this->get(route('options.index', ['tab' => 'bulk-imports']))
            ->assertOk()
            ->assertSee('一括インポートの完了処理に失敗しました。')
            ->assertSee(__('Already in library.'))
            ->assertSee('地域制限によりアクセスできないDLSite作品')
            ->assertSee('DLSite data was fetched, but these images could not be downloaded: cover.jpg, sample_1.jpg')
            ->assertSee('RJ000000704 -')
            ->assertSee('Unexpected scraper detail')
            ->assertSee('Legacy warning: <upstream detail>')
            ->assertDontSee('Legacy warning: <upstream detail>', false);

        Option::setUiLanguage(UiLanguage::English);
        $this->get(route('options.index', ['tab' => 'bulk-imports']))
            ->assertOk()
            ->assertSee('Bulk import finalization failed.')
            ->assertSee('Already in library.')
            ->assertSee('GeoBlocked DLSite work')
            ->assertSee('DLSite data was fetched, but these images could not be downloaded: cover.jpg, sample_1.jpg')
            ->assertSee('Legacy warning: <upstream detail>');
    }

    public function test_completed_history_stops_polling_when_no_run_is_active(): void
    {
        BulkImportRun::create([
            'status' => BulkImportRunStatus::Completed,
            'input_snapshot' => [],
            'total_count' => 2,
            'processed_count' => 2,
            'imported_count' => 2,
            'completed_at' => now(),
        ]);

        Livewire::test(BulkImportRunCard::class, ['runId' => BulkImportRun::query()->value('id')])
            ->assertSee('Completed')
            ->assertDontSee('wire:poll.visible.2s', false);
    }

    public function test_history_is_paginated_at_ten_runs_per_page(): void
    {
        foreach (range(1, 11) as $number) {
            BulkImportRun::create([
                'status' => BulkImportRunStatus::Completed,
                'input_snapshot' => [],
                'total_count' => 1,
                'processed_count' => 1,
                'imported_count' => 1,
                'completed_at' => now(),
            ]);
        }

        $component = Livewire::test(OptionsBulkImports::class)
            ->assertSee('Showing 1-10 of 11')
            ->assertViewHas(
                'runs',
                fn($runs): bool =>
                $runs->count() === 10
                    && $runs->total() === 11
                    && $runs->currentPage() === 1
            );

        $component
            ->call('nextPage', 'bulkImportsPage')
            ->assertSet('paginators.bulkImportsPage', 2)
            ->assertSee('Showing 11-11 of 11')
            ->assertViewHas(
                'runs',
                fn($runs): bool =>
                $runs->count() === 1
                    && $runs->total() === 11
                    && $runs->currentPage() === 2
            );
    }

    public function test_parent_refreshes_cleanup_availability_when_active_child_finishes(): void
    {
        $run = BulkImportRun::create([
            'status' => BulkImportRunStatus::Running,
            'input_snapshot' => [],
            'total_count' => 1,
            'started_at' => now(),
        ]);

        $component = Livewire::test(OptionsBulkImports::class)
            ->assertSee('data-bulk-import-cleanup-unavailable="true"', false);

        $run->forceFill([
            'status' => BulkImportRunStatus::Completed,
            'processed_count' => 1,
            'completed_at' => now(),
        ])->save();

        $component
            ->dispatch('bulk-import-run-finished')
            ->assertSee('data-bulk-import-cleanup-unavailable="false"', false);
    }

    public function test_cleanup_removes_all_history_when_no_run_is_active(): void
    {
        $completed = BulkImportRun::create([
            'status' => BulkImportRunStatus::Completed,
            'input_snapshot' => [],
            'total_count' => 1,
            'processed_count' => 1,
            'imported_count' => 1,
            'completed_at' => now(),
        ]);
        $completedItem = $completed->items()->create([
            'position' => 1,
            'product_id' => 'RJ000000601',
            'status' => BulkImportItemStatus::Imported,
            'completed_at' => now(),
        ]);

        $failed = BulkImportRun::create([
            'status' => BulkImportRunStatus::Failed,
            'input_snapshot' => [],
            'total_count' => 1,
            'processed_count' => 0,
            'failed_at' => now(),
            'error' => 'Worker failed',
        ]);

        Livewire::test(OptionsBulkImports::class)
            ->assertSee('data-bulk-import-cleanup-unavailable="false"', false)
            ->assertSee('Clean up Bulk Import history')
            ->call('askCleanup')
            ->assertSet('confirmingCleanup', true)
            ->assertSee('Permanently delete all Bulk Import history?')
            ->call('cleanup')
            ->assertSet('confirmingCleanup', false)
            ->assertSet('cleanupNotice', 'Bulk Import history cleaned up.');

        $this->assertDatabaseMissing('bulk_import_runs', ['id' => $completed->id]);
        $this->assertDatabaseMissing('bulk_import_runs', ['id' => $failed->id]);
        $this->assertDatabaseMissing('bulk_import_items', ['id' => $completedItem->id]);
    }

    public function test_cleanup_is_unavailable_while_a_run_is_active(): void
    {
        BulkImportRun::create([
            'status' => BulkImportRunStatus::Running,
            'input_snapshot' => [],
            'total_count' => 1,
            'started_at' => now(),
        ]);

        Livewire::test(OptionsBulkImports::class)
            ->assertSee('data-bulk-import-cleanup-unavailable="true"', false)
            ->call('askCleanup')
            ->assertSet('confirmingCleanup', false)
            ->assertHasErrors(['cleanup']);
    }

    public function test_cleanup_rechecks_active_runs_after_confirmation(): void
    {
        $completed = BulkImportRun::create([
            'status' => BulkImportRunStatus::Completed,
            'input_snapshot' => [],
            'total_count' => 1,
            'processed_count' => 1,
            'imported_count' => 1,
            'completed_at' => now(),
        ]);

        $component = Livewire::test(OptionsBulkImports::class)
            ->call('askCleanup')
            ->assertSet('confirmingCleanup', true);

        $active = BulkImportRun::create([
            'status' => BulkImportRunStatus::Queued,
            'input_snapshot' => [],
            'total_count' => 1,
        ]);

        $component
            ->call('cleanup')
            ->assertSet('confirmingCleanup', false)
            ->assertHasErrors(['cleanup']);

        $this->assertDatabaseHas('bulk_import_runs', ['id' => $completed->id]);
        $this->assertDatabaseHas('bulk_import_runs', ['id' => $active->id]);
    }

    public function test_cleanup_is_blocked_while_bulk_import_lifecycle_lock_is_held(): void
    {
        $run = BulkImportRun::create([
            'status' => BulkImportRunStatus::Completed,
            'input_snapshot' => [],
            'total_count' => 1,
            'processed_count' => 1,
            'imported_count' => 1,
            'completed_at' => now(),
        ]);
        $lock = Cache::lock(BulkImportRun::LIFECYCLE_LOCK, BulkImportRun::LIFECYCLE_LOCK_SECONDS);

        $this->assertTrue($lock->get());

        try {
            try {
                app(BulkImportCleanupService::class)->cleanup();
                $this->fail('Expected the shared Bulk Import lifecycle lock to block cleanup.');
            } catch (RuntimeException $exception) {
                $this->assertSame(BulkImportCleanupService::BUSY_MESSAGE, $exception->getMessage());
            }
        } finally {
            $lock->release();
        }

        $this->assertDatabaseHas('bulk_import_runs', ['id' => $run->id]);
    }
}
