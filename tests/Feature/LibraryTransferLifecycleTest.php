<?php

namespace Tests\Feature;

use App\Enums\LibraryTransferAction;
use App\Enums\LibraryTransferDirection;
use App\Enums\LibraryTransferOperation;
use App\Enums\LibraryTransferPartStatus;
use App\Enums\LibraryTransferRunStatus;
use App\Jobs\LibraryTransferJob;
use App\Livewire\OptionsTransferRun;
use App\Livewire\OptionsTransfers;
use App\Models\LibraryTransferRun;
use App\Models\Option;
use App\Models\Product;
use App\Support\Transfers\InvalidArchive;
use App\Support\Transfers\LibraryTransferCleanupService;
use App\Support\Transfers\TransferArchive;
use App\Support\Transfers\TransferJobDispatcher;
use App\Support\Transfers\TransferStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Concerns\InteractsWithLibraryTransfers;
use Tests\TestCase;
use ZipArchive;

class LibraryTransferLifecycleTest extends TestCase
{
    use InteractsWithLibraryTransfers;

    public function test_transfer_options_update_scopes_and_show_latest_run_shortcuts(): void
    {
        Product::factory()->create(['id' => 'RJ123456']);

        $component = Livewire::test(OptionsTransfers::class)
            ->assertSet('sizeChoice', '256')
            ->assertSee('1024 MiB')
            ->assertSee('8192 MiB')
            ->set('scopes', ['works', 'images', 'options'])
            ->set('scopes', ['options'])
            ->assertSet('scopes', ['options']);

        $export = LibraryTransferRun::create([
            'direction' => 'export',
            'status' => 'ready',
            'settings' => [],
        ]);
        $component->call('$refresh')
            ->assertSee('Latest export')
            ->assertSee('href="' . route('options.transfers.show', $export) . '"', false);

        $import = LibraryTransferRun::create([
            'direction' => 'import',
            'status' => 'review',
            'settings' => [],
        ]);
        $component->call('$refresh')
            ->assertSee('Latest import')
            ->assertSee('href="' . route('options.transfers.show', $import) . '"', false)
            ->assertSeeInOrder(['Latest export', 'Latest import']);
    }

    public function test_import_is_not_created_until_the_explicit_start_action_returns_upload_routes(): void
    {
        $uploadMaxBytes = $this->service()->uploadLimitBytes();
        $configuredLimit = UploadedFile::getMaxFilesize();
        $this->assertSame($configuredLimit >= PHP_INT_MAX ? null : (int) $configuredLimit, $uploadMaxBytes);

        $component = Livewire::test(OptionsTransfers::class)
            ->assertSee('data-transfer-upload', false)
            ->assertSee('data-start-import', false)
            ->assertSee('wire:ignore', false)
            ->assertSee('type="file" multiple', false)
            ->assertSee('data-upload-max-bytes="' . $uploadMaxBytes . '"', false)
            ->assertSee('data-file-too-large="Exceeds the __SIZE__ MiB upload limit."', false)
            ->assertSee('data-uploaded="Uploaded"', false)
            ->assertSee('data-failed="Some files could not be uploaded."', false);

        $this->assertDatabaseCount('library_transfer_runs', 0);

        try {
            (new OptionsTransfers)->startImport(0);
            $this->fail('Starting without an uploadable ZIP must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('transfer', $exception->errors());
        }
        $this->assertDatabaseCount('library_transfer_runs', 0);

        $component->call('startImport', 1);
        $run = LibraryTransferRun::firstOrFail();

        $component->assertReturned([
            'runId' => $run->id,
            'uploadUrl' => route('options.transfers.upload', $run),
            'showUrl' => route('options.transfers.show', $run),
        ]);
        $this->assertSame(LibraryTransferRunStatus::Uploading, $run->status);
    }

    #[DataProvider('supersedableTransferStatusProvider')]
    public function test_starting_a_transfer_cancels_unfinished_runs_in_the_same_direction(string $direction, string $status): void
    {
        $previous = LibraryTransferRun::create([
            'direction' => $direction,
            'status' => $status,
            'settings' => [],
        ]);

        $replacement = $direction === 'export'
            ? $this->service()->export(['options'], 'all', [])
            : $this->service()->startImport();

        $this->assertSame(LibraryTransferRunStatus::Cancelled, $previous->fresh()->status);
        $this->assertSame(2, $previous->fresh()->generation);
        $this->assertSame(LibraryTransferDirection::from($direction), $replacement->direction);
        $this->assertNotSame($previous->id, $replacement->id);
    }

    public static function supersedableTransferStatusProvider(): iterable
    {
        foreach (
            [
                'export' => ['queued', 'planning', 'awaiting_confirmation', 'building', 'failed'],
                'import' => ['uploading', 'waiting_for_parts', 'analyzing', 'review', 'failed'],
            ] as $direction => $statuses
        ) {
            foreach ($statuses as $status) {
                yield $direction . ' ' . $status => [$direction, $status];
            }
        }
    }

    public function test_starting_replacements_preserves_finished_and_cancelled_history(): void
    {
        $readyExport = LibraryTransferRun::create(['direction' => 'export', 'status' => 'ready', 'settings' => []]);
        $cancelledExport = LibraryTransferRun::create(['direction' => 'export', 'status' => 'cancelled', 'settings' => []]);
        $completedImport = LibraryTransferRun::create(['direction' => 'import', 'status' => 'completed', 'settings' => []]);
        $warningImport = LibraryTransferRun::create(['direction' => 'import', 'status' => 'completed_with_warnings', 'settings' => []]);
        $cancelledImport = LibraryTransferRun::create(['direction' => 'import', 'status' => 'cancelled', 'settings' => []]);

        $this->service()->export(['options'], 'all', []);
        $this->service()->startImport();

        $this->assertSame(LibraryTransferRunStatus::Ready, $readyExport->fresh()->status);
        $this->assertSame(LibraryTransferRunStatus::Cancelled, $cancelledExport->fresh()->status);
        $this->assertSame(LibraryTransferRunStatus::Completed, $completedImport->fresh()->status);
        $this->assertSame(LibraryTransferRunStatus::CompletedWithWarnings, $warningImport->fresh()->status);
        $this->assertSame(LibraryTransferRunStatus::Cancelled, $cancelledImport->fresh()->status);
    }

    public function test_new_import_is_blocked_while_an_existing_import_is_applying(): void
    {
        $applying = LibraryTransferRun::create(['direction' => 'import', 'status' => 'applying', 'settings' => []]);

        try {
            $this->service()->startImport();
            $this->fail('A non-cancellable Apply operation must block a replacement import.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Wait for the current import to finish applying before starting another import.', $exception->getMessage());
        }

        $this->assertDatabaseCount('library_transfer_runs', 1);
        $this->assertSame(LibraryTransferRunStatus::Applying, $applying->fresh()->status);
    }

    public function test_superseded_generation_rejects_delayed_export_jobs(): void
    {
        $previous = LibraryTransferRun::create([
            'direction' => 'export',
            'status' => 'queued',
            'settings' => ['scopes' => ['options'], 'product_ids' => []],
        ]);
        $job = new LibraryTransferJob($previous->id, LibraryTransferOperation::Plan, generation: $previous->generation);

        $this->service()->export(['options'], 'all', []);
        $this->executeJob($job);
        $job->failed(new RuntimeException('Delayed failure'));

        $this->assertSame(LibraryTransferRunStatus::Cancelled, $previous->fresh()->status);
        $this->assertSame(2, $previous->fresh()->generation);
        $this->assertSame(0, $previous->parts()->count());
    }

    public function test_import_upload_controls_disappear_when_analysis_starts(): void
    {
        foreach (['uploading', 'waiting_for_parts'] as $status) {
            $run = LibraryTransferRun::create([
                'direction' => 'import',
                'status' => $status,
                'stage' => 'Validating images part 1',
                'settings' => [],
            ]);

            $view = Livewire::test(OptionsTransferRun::class, ['run' => $run])
                ->assertSee('Waiting for ZIP files')
                ->assertSee('Attach ZIP files from Archive parts list below')
                ->assertSee('type="button" data-upload', false);
            $this->assertSame('Waiting for ZIP files', $view->instance()->progressData['description']);

            $run->parts()->create(['kind' => 'data', 'number' => 2, 'status' => 'pending', 'filename' => 'pending.zip', 'manifest' => []]);
            $view->call('$refresh')->assertSee('Validating data part 2');
            $this->assertSame('Validating data part 2', $view->instance()->progressData['description']);
        }

        foreach (['analyzing', 'review', 'applying', 'completed', 'completed_with_warnings', 'failed', 'cancelled'] as $status) {
            $run = LibraryTransferRun::create([
                'direction' => 'import',
                'status' => $status,
                'settings' => [],
            ]);

            $this->get(route('options.transfers.show', $run))
                ->assertOk()
                ->assertDontSee('Attach ZIP files from Archive parts list below')
                ->assertDontSee('type="button" data-upload', false);
        }
    }

    public function test_attached_part_is_kept_without_showing_another_uploader_while_validation_runs(): void
    {
        $run = LibraryTransferRun::create([
            'direction' => 'import',
            'status' => 'waiting_for_parts',
            'settings' => [
                'manifest' => ['exported_at' => '2026-09-09T15:43:27Z'],
                'parts' => ['data' => 1, 'images' => 0],
            ],
        ]);
        $part = $run->parts()->create([
            'kind' => 'data',
            'number' => 1,
            'status' => 'pending',
            'path' => $run->directory() . '/attached.zip',
            'filename' => 'dlsite-list_20260909_154327Z.data.part01-of-01.zip',
            'manifest' => [],
        ]);

        $this->get(route('options.transfers.show', $run))
            ->assertOk()
            ->assertSee($part->filename)
            ->assertDontSee('Attach ZIP files from Archive parts list below')
            ->assertDontSee('type="button" data-upload', false)
            ->assertDontSee('Waiting for data ZIP file:')
            ->assertDontSee('Waiting for image ZIP file:');

        $this->assertDatabaseHas('library_transfer_parts', ['id' => $part->id, 'status' => 'pending']);
    }

    public function test_archive_parts_are_hidden_while_an_import_is_processing_or_showing_review(): void
    {
        foreach ([
            LibraryTransferRunStatus::Analyzing,
            LibraryTransferRunStatus::Applying,
            LibraryTransferRunStatus::Review,
            LibraryTransferRunStatus::Completed,
            LibraryTransferRunStatus::CompletedWithWarnings,
        ] as $status) {
            $run = LibraryTransferRun::create([
                'direction' => LibraryTransferDirection::Import,
                'status' => $status,
                'settings' => ['parts' => ['data' => 1, 'images' => 2]],
            ]);

            $view = Livewire::test(OptionsTransferRun::class, ['run' => $run]);

            $this->assertCount(0, $view->instance()->partData['partSlots']);
        }
    }

    public function test_attached_invalid_part_replacement_is_not_requested_again_while_validation_is_pending(): void
    {
        $run = LibraryTransferRun::create([
            'direction' => 'import',
            'status' => 'waiting_for_parts',
            'settings' => [
                'manifest' => ['exported_at' => '2026-09-09T15:43:27Z'],
                'parts' => ['data' => 1, 'images' => 0],
            ],
        ]);
        $run->parts()->create([
            'kind' => 'data',
            'number' => 1,
            'status' => 'invalid',
            'path' => $run->directory() . '/invalid.zip',
            'filename' => 'dlsite-list_20260909_154327Z.data.part01-of-01.zip',
            'manifest' => [],
            'candidate' => ['path' => $run->directory() . '/replacement.zip', 'bytes' => 100],
        ]);

        $this->get(route('options.transfers.show', $run))
            ->assertOk()
            ->assertSee('Replacement file attached; validation is pending.')
            ->assertDontSee('Attach ZIP files from Archive parts list below')
            ->assertDontSee('Expected replacement file:');
    }

    public function test_global_cleanup_requires_confirmation_and_preserves_applied_library_data(): void
    {
        $root = storage_path('framework/testing/disks/local-cleanup-' . uniqid());

        Storage::set('local', Storage::build([
            'driver' => 'local',
            'root' => $root,
            'throw' => false,
        ]));

        $product = Product::factory()->create(['id' => 'RJ123456']);
        Storage::disk('local')->put("Works/{$product->id}.json", '{"canonical":true}');
        Storage::disk('public')->put("Works/{$product->id}/cover.jpg", 'canonical-cover');

        $export = LibraryTransferRun::create(['direction' => 'export', 'status' => 'awaiting_confirmation', 'settings' => []]);
        $export->parts()->create([
            'kind' => 'data',
            'number' => 1,
            'status' => 'pending',
            'path' => $export->directory() . '/export.zip',
            'filename' => 'export.zip',
            'manifest' => [],
        ]);
        Storage::disk('local')->put($export->directory() . '/export.zip', 'archive');

        $import = LibraryTransferRun::create(['direction' => 'import', 'status' => 'review', 'settings' => []]);
        $import->items()->create([
            'section' => 'options',
            'category' => 'options',
            'entity_key' => 'ui_language',
            'identity_hash' => hash('sha256', 'ui_language'),
            'baseline' => [],
            'incoming' => [],
        ]);
        Storage::disk('public')->put($import->directory() . '/preview.jpg', 'preview');

        $component = Livewire::test(OptionsTransfers::class)
            ->assertSee('data-transfer-cleanup-unavailable="false"', false)
            ->call('cleanup')
            ->assertSet('confirmingCleanup', false);
        $this->assertDatabaseCount('library_transfer_runs', 2);

        $component->call('askCleanup')
            ->assertSet('confirmingCleanup', true)
            ->assertSee('Permanently delete all transfer history, archives, and staged files?')
            ->assertSee('Cleaning up transfer history...')
            ->assertSee('wire:loading wire:target="cleanup"', false)
            ->call('cleanup')
            ->assertHasNoErrors('cleanup')
            ->assertSet('confirmingCleanup', false)
            ->assertSet('cleanupNotice', 'Transfer history cleaned up.');

        $this->assertDatabaseCount('library_transfer_runs', 0);
        $this->assertDatabaseCount('library_transfer_parts', 0);
        $this->assertDatabaseCount('library_import_items', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('Transfers'));
        $this->assertSame([$import->directory() . '/preview.jpg'], Storage::disk('public')->allFiles('Transfers'));
        $this->assertFileExists(Storage::disk('local')->path("Works/{$product->id}.json"));
        $this->assertFileExists(Storage::disk('public')->path("Works/{$product->id}/cover.jpg"));
    }

    #[DataProvider('activeTransferStatusProvider')]
    public function test_global_cleanup_is_unavailable_during_active_transfers(string $status): void
    {
        LibraryTransferRun::create(['direction' => str_contains($status, 'build') || in_array($status, ['queued', 'planning'], true) ? 'export' : 'import', 'status' => $status, 'settings' => []]);

        Livewire::test(OptionsTransfers::class)
            ->assertSee('data-transfer-cleanup-unavailable="true"', false)
            ->call('askCleanup')
            ->assertSet('confirmingCleanup', false)
            ->assertHasErrors('cleanup')
            ->assertSee(LibraryTransferCleanupService::UNAVAILABLE_MESSAGE);
    }

    public static function activeTransferStatusProvider(): iterable
    {
        foreach (LibraryTransferRunStatus::cases() as $status) {
            if ($status->isCleanupActive()) {
                yield $status->value => [$status->value];
            }
        }
    }

    public function test_global_cleanup_waits_for_cancelled_run_work_to_release_its_lock(): void
    {
        $run = LibraryTransferRun::create(['direction' => 'export', 'status' => 'cancelled', 'settings' => []]);
        $lock = Cache::lock('transfer-run-' . $run->id, config('transfers.lock_seconds'));
        $this->assertTrue($lock->get());

        try {
            Livewire::test(OptionsTransfers::class)
                ->assertSee('data-transfer-cleanup-unavailable="false"', false)
                ->call('askCleanup')
                ->assertSet('confirmingCleanup', true)
                ->call('cleanup')
                ->assertSet('confirmingCleanup', false)
                ->assertHasErrors('cleanup');
        } finally {
            $lock->release();
        }

        Livewire::test(OptionsTransfers::class)
            ->assertSee('data-transfer-cleanup-unavailable="false"', false);
    }

    public function test_transfer_creation_does_not_overlap_global_cleanup(): void
    {
        $lock = Cache::lock(LibraryTransferRun::LIFECYCLE_LOCK, config('transfers.lock_seconds'));
        $this->assertTrue($lock->get());

        try {
            $this->expectExceptionMessage(LibraryTransferCleanupService::BUSY_MESSAGE);
            $this->service()->startImport();
        } finally {
            $lock->release();
        }
    }

    public function test_global_cleanup_rechecks_active_runs_after_confirmation(): void
    {
        $run = LibraryTransferRun::create(['direction' => 'import', 'status' => 'review', 'settings' => []]);
        $component = Livewire::test(OptionsTransfers::class)
            ->call('askCleanup')
            ->assertSet('confirmingCleanup', true);

        $run->update(['status' => 'uploading']);

        $component->call('cleanup')
            ->assertSet('confirmingCleanup', false)
            ->assertHasErrors('cleanup')
            ->assertSee(LibraryTransferCleanupService::UNAVAILABLE_MESSAGE);

        $this->assertDatabaseCount('library_transfer_runs', 1);
    }

    public function test_cancel_during_planning_and_retry_after_ready_part_do_not_strand_runs(): void
    {
        $run = $this->service()->export(['options'], 'all', []);
        $this->service()->action($run, LibraryTransferAction::Cancel);
        $this->executeJob(new LibraryTransferJob($run->id, LibraryTransferOperation::Plan, token: $run->job_token));
        $this->assertSame(0, $run->parts()->count());
        $export = $this->exported(['options']);
        $export->update(['status' => 'building', 'processed' => 0]);
        $this->archive()->build($export->parts()->first());
        $this->assertSame(LibraryTransferRunStatus::Ready, $export->fresh()->status);
        $this->assertSame(1, $export->fresh()->processed);
    }

    public function test_transfer_jobs_do_not_expire_while_waiting_but_fail_on_timeout_or_repeated_exceptions(): void
    {
        $job = new LibraryTransferJob(123, LibraryTransferOperation::Plan);
        $queue = app('queue')->connection('database');

        $this->assertSame(0, $queue->getJobTries($job));
        $this->assertNull($queue->getJobExpiration($job));
        $this->assertSame(3, $job->maxExceptions);
        $this->assertTrue($job->failOnTimeout);
        $this->assertSame(config('transfers.job_timeout'), $job->timeout);
    }

    public function test_locale_reload_size_validation_and_background_queue_guard(): void
    {
        Livewire::test(OptionsTransfers::class)->set('sizeChoice', 'custom')->set('customMib', '1.5')->call('saveSize')->assertHasErrors('customMib');
        Livewire::test(OptionsTransfers::class)
            ->set('sizeChoice', '8192')
            ->call('saveSize')
            ->assertHasNoErrors()
            ->assertSeeInOrder(['Save Export size', 'Export part size saved.', 'Include']);
        $this->assertSame(8192, Option::exportPartMib());
        $run = $this->imported($this->exported(['options']));
        $view = Livewire::test(OptionsTransferRun::class, ['run' => $run]);
        Option::setUiLanguage('ja');
        app()->setLocale('ja');
        $view->call('$refresh')->assertRedirect(route('options.transfers.show', $run));
        config(['queue.default' => 'sync']);
        $this->expectExceptionMessage('database queue');
        $this->service()->startImport();
    }

    public function test_failed_image_validation_job_keeps_data_only_recovery_available(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);
        $run = $this->service()->startImport();
        foreach ($export->parts()->get() as $part) {
            $upload = $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($part->path), $part->filename, null, null, true));
            if ($part->kind === 'data') {
                $this->service()->inspect($run->fresh(), $upload->id);
            } else {
                $upload->refresh();
                (new LibraryTransferJob($run->id, LibraryTransferOperation::Inspect, $upload->id, $run->generation, $upload->upload_path, $upload->validation_token))->failed(new RuntimeException('Timed out'));
            }
        }
        $this->assertSame(LibraryTransferRunStatus::WaitingForParts, $run->fresh()->status);
        $this->assertSame(LibraryTransferPartStatus::Invalid, $run->parts()->where('kind', 'images')->first()->status);
        $this->service()->action($run->fresh(), LibraryTransferAction::WithoutImages);
        $this->service()->analyze($run->fresh());
        $this->assertSame(LibraryTransferRunStatus::Review, $run->fresh()->status);
    }

    public function test_review_switches_to_available_section_after_analysis_and_hides_uploader(): void
    {
        $run = $this->service()->startImport();
        $view = Livewire::test(OptionsTransferRun::class, ['run' => $run])
            ->assertSee('data-transfer-upload', false)
            ->assertSee('class="form-control file-upload-input"', false)
            ->assertSee('data-file-progress', false)
            ->assertSee('data-total-progress', false);
        $this->review()->item($run, 'options', 'options', Option::INDEX_PER_PAGE, 100, 50);
        $run->update(['status' => 'review']);
        $view->call('$refresh')->assertSet('section', 'options')->assertSet('category', 'options')
            ->assertDontSee('data-transfer-upload', false)->assertSee('refetch-change-comparison', false);
        $run->update(['status' => 'completed']);
        $view->call('$refresh')->assertDontSee('wire:poll', false);
    }

    public function test_terminal_runs_and_new_generations_ignore_late_jobs_and_failures(): void
    {
        $run = $this->exported(['options']);
        $job = new LibraryTransferJob($run->id, LibraryTransferOperation::Build, $run->parts()->first()->id, $run->generation, token: $run->job_token);
        $job->failed(new RuntimeException('Late callback'));
        $this->assertSame(LibraryTransferRunStatus::Ready, $run->fresh()->status);
        $old = $run->fresh();
        $run->update(['status' => 'building', 'generation' => 2]);
        $job->failed(new RuntimeException('Obsolete callback'));
        $this->assertSame(LibraryTransferRunStatus::Building, $run->fresh()->status);
        $this->assertFalse(app(TransferJobDispatcher::class)->checkpoint($old, ['status' => LibraryTransferRunStatus::Failed], LibraryTransferOperation::Plan));
        $this->service()->action($run, LibraryTransferAction::Cancel);
        $this->assertFalse(app(TransferJobDispatcher::class)->checkpoint($run, ['status' => LibraryTransferRunStatus::Building], LibraryTransferOperation::Build, $job->partId));
        $this->assertSame(LibraryTransferRunStatus::Cancelled, $run->fresh()->status);
    }

    public function test_operational_build_retry_advances_generation_and_adopts_published_zip(): void
    {
        $run = $this->exported(['options']);
        $part = $run->parts()->first();
        $run->update(['status' => 'building']);
        $part->update(['status' => 'pending']);
        $job = new LibraryTransferJob($run->id, LibraryTransferOperation::Build, $part->id, $run->generation, token: $run->job_token);
        $job->failed(new RuntimeException('Storage temporarily unavailable'));
        $this->assertSame(LibraryTransferRunStatus::Failed, $run->fresh()->status);
        $this->service()->action($run->fresh(), LibraryTransferAction::Retry);
        $this->assertSame(2, $run->fresh()->generation);
        $this->assertSame(LibraryTransferPartStatus::Pending, $part->fresh()->status);
        $job->failed(new RuntimeException('Late old attempt'));
        $this->assertSame(LibraryTransferRunStatus::Building, $run->fresh()->status);
        $run->refresh();
        $this->executeJob(new LibraryTransferJob($run->id, LibraryTransferOperation::Build, $part->id, 2, token: $run->job_token));
        $this->assertSame(LibraryTransferRunStatus::Ready, $run->fresh()->status);
    }

    public function test_export_creation_rolls_back_if_database_queue_insertion_fails(): void
    {
        $before = LibraryTransferRun::count();
        Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('Queue insertion failed'));
        try {
            $this->service()->export(['options'], 'all', []);
            $this->fail('Queue insertion must fail this request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Queue insertion failed', $exception->getMessage());
        }
        $this->assertSame($before, LibraryTransferRun::count());
    }

    public function test_manifest_numeric_strings_are_rejected_and_timing_and_disk_guards_are_enforced(): void
    {
        $export = $this->exported(['options']);
        $part = $export->parts()->firstOrFail();
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($part->path));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['schema_version'] = '1';
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();
        try {
            $this->archive()->inspect(Storage::disk('local')->path($part->path));
            $this->fail('Numeric strings are not schema-v1 JSON integers.');
        } catch (InvalidArchive $exception) {
            $this->assertStringContainsString('JSON integers', $exception->getMessage());
        }
        config(['transfers.job_timeout' => config('transfers.lock_seconds')]);
        try {
            $this->service()->startImport();
            $this->fail('Worker timeout must expire before locks.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('worker timeout < lock lifetime', $exception->getMessage());
        }
        config(['transfers.disk_reserve_bytes' => PHP_INT_MAX]);
        $this->expectExceptionMessage('Not enough free disk space');
        TransferArchive::space(0, Storage::disk('local')->path(''));
    }

    public function test_stale_run_and_part_tokens_make_jobs_and_failure_callbacks_harmless(): void
    {
        $run = LibraryTransferRun::create([
            'direction' => 'export',
            'status' => 'building',
            'job_token' => 2,
            'settings' => ['scopes' => ['options']],
        ]);
        $part = $run->parts()->create([
            'kind' => 'data',
            'number' => 1,
            'status' => 'pending',
            'filename' => 'part.zip',
            'path' => $run->directory() . '/archives/part.zip',
            'manifest' => [],
        ]);
        $job = new LibraryTransferJob($run->id, LibraryTransferOperation::Build, $part->id, $run->generation, token: 1);

        $this->executeJob($job);
        $job->failed(new RuntimeException('Late failure'));

        $this->assertSame(LibraryTransferRunStatus::Building, $run->fresh()->status);
        $this->assertSame(LibraryTransferPartStatus::Pending, $part->fresh()->status);

        $import = LibraryTransferRun::create(['direction' => 'import', 'status' => 'waiting_for_parts', 'settings' => []]);
        $upload = $import->parts()->create([
            'kind' => 'data',
            'number' => 1,
            'status' => 'pending',
            'filename' => 'upload.zip',
            'upload_path' => $import->directory() . '/uploads/upload.zip',
            'manifest' => [],
            'validation_token' => 2,
        ]);
        $validation = new LibraryTransferJob($import->id, LibraryTransferOperation::Inspect, $upload->id, $import->generation, $upload->upload_path, 1);
        $this->executeJob($validation);
        $validation->failed(new RuntimeException('Late validation failure'));

        $this->assertSame(LibraryTransferRunStatus::WaitingForParts, $import->fresh()->status);
        $this->assertSame(LibraryTransferPartStatus::Pending, $upload->fresh()->status);
    }

    public function test_missing_published_export_is_changed_to_a_recoverable_failure(): void
    {
        $run = $this->exported(['options']);
        $part = $run->parts()->firstOrFail();
        Storage::disk('local')->delete($part->path);

        $this->assertFalse($this->service()->exportPartAvailable($run, $part));
        $this->assertSame(LibraryTransferRunStatus::Failed, $run->fresh()->status);
        $this->assertSame(LibraryTransferPartStatus::Pending, $part->fresh()->status);
        $this->assertSame('build', $run->fresh()->settings['retry_operation']);
    }

    public function test_transfer_storage_sweep_removes_only_unreferenced_private_temporary_files(): void
    {
        $run = LibraryTransferRun::create(['direction' => 'import', 'status' => 'waiting_for_parts', 'settings' => []]);
        $kept = $run->directory() . '/uploads/kept.zip';
        $orphan = $run->directory() . '/uploads/orphan.zip';
        Storage::disk('local')->put($kept, 'kept');
        Storage::disk('local')->put($orphan, 'orphan');
        $uploadLock = Cache::lock('transfer-upload-' . $run->id, config('transfers.lock_seconds'));
        $this->assertTrue($uploadLock->get());
        try {
            app(TransferStorage::class)->sweep($run);
            $this->assertTrue(Storage::disk('local')->exists($kept));
            $this->assertTrue(Storage::disk('local')->exists($orphan));
        } finally {
            $uploadLock->release();
        }
        $run->parts()->create([
            'kind' => 'data',
            'number' => 1,
            'status' => 'pending',
            'filename' => 'kept.zip',
            'upload_path' => $kept,
            'manifest' => [],
        ]);

        app(TransferStorage::class)->sweep($run);

        $this->assertTrue(Storage::disk('local')->exists($kept));
        $this->assertFalse(Storage::disk('local')->exists($orphan));
        $this->assertSame([], Storage::disk('public')->allFiles('Transfers'));
    }
}
