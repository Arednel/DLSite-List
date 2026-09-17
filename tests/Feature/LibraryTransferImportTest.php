<?php

namespace Tests\Feature;

use App\Enums\LibraryTransferAction;
use App\Enums\LibraryTransferOperation;
use App\Enums\LibraryTransferPartStatus;
use App\Enums\LibraryTransferRunStatus;
use App\Jobs\LibraryTransferJob;
use App\Livewire\OptionsTransferRun;
use App\Models\LibraryImportItem;
use App\Models\LibraryTransferRun;
use App\Models\Product;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Concerns\InteractsWithLibraryTransfers;
use Tests\TestCase;
use ZipArchive;

class LibraryTransferImportTest extends TestCase
{
    use InteractsWithLibraryTransfers;

    public function test_import_requests_exact_missing_filenames_without_zero_image_summaries(): void
    {
        $run = LibraryTransferRun::create([
            'direction' => 'import',
            'status' => 'waiting_for_parts',
            'settings' => [
                'manifest' => ['exported_at' => '2026-09-09T15:43:27Z'],
                'parts' => ['data' => 2, 'images' => 0],
                'without_images' => false,
            ],
        ]);
        $run->parts()->create([
            'kind' => 'data',
            'number' => 1,
            'status' => 'valid',
            'path' => $run->directory() . '/one.zip',
            'filename' => 'dlsite-list_20260909_154327Z.data.part01-of-02.zip',
            'manifest' => [],
        ]);

        $this->get(route('options.transfers.show', $run))
            ->assertOk()
            ->assertSee('Attach the required ZIP files listed below.')
            ->assertSee('Attach ZIP files from Archive parts list below')
            ->assertSee('type="button" data-upload', false)
            ->assertSee('class="transfer-upload-progress" hidden data-progress', false)
            ->assertSee('<summary class="transfer-parts__heading">Archive parts</summary>', false)
            ->assertSee('Waiting for data ZIP file: dlsite-list_20260909_154327Z.data.part02-of-02.zip')
            ->assertDontSee('Images: 0 / 0');
    }

    public function test_image_part_uploaded_first_is_retained_and_requests_the_exact_data_part(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);
        $data = $export->parts()->where('kind', 'data')->firstOrFail();
        $image = $export->parts()->where('kind', 'images')->firstOrFail();
        $run = $this->service()->startImport();

        $this->postJson(route('options.transfers.upload', $run), [
            'archive' => new UploadedFile(
                Storage::disk('local')->path($image->path),
                $image->filename,
                'application/zip',
                null,
                true,
            ),
        ])->assertOk();
        $uploaded = $run->parts()->where('kind', 'images')->firstOrFail();

        $this->assertSame('images', $uploaded->kind);
        $this->assertSame($image->filename, $run->parts()->firstOrFail()->filename);
        $view = Livewire::test(OptionsTransferRun::class, ['run' => $run->fresh()])
            ->assertSee('data-upload-max-bytes="' . $this->service()->uploadLimitBytes() . '"', false)
            ->assertSee('Attached image ZIP file: ' . $image->filename)
            ->assertSee('Waiting for data ZIP file: ' . $data->filename)
            ->assertDontSee('Waiting for image ZIP file:')
            ->assertSee('Data: 0 / 1');

        $this->service()->inspect($run->fresh(), $uploaded->id);
        $this->assertSame(LibraryTransferPartStatus::Valid, $uploaded->fresh()->status);
        $view->call('$refresh')
            ->assertSee('Attached image ZIP file: ' . $image->filename)
            ->assertSee('Waiting for data ZIP file: ' . $data->filename);

        $this->postJson(route('options.transfers.upload', $run), [
            'archive' => new UploadedFile(
                Storage::disk('local')->path($data->path),
                $data->filename,
                'application/zip',
                null,
                true,
            ),
        ])->assertOk();
        $uploadedData = $run->parts()->where('kind', 'data')->firstOrFail();
        $this->service()->inspect($run->fresh(), $uploadedData->id);

        $this->assertSame(LibraryTransferPartStatus::Valid, $uploaded->fresh()->status);
        $this->assertSame(LibraryTransferPartStatus::Valid, $uploadedData->fresh()->status);
        $this->assertCount(2, $run->parts()->get());
        $this->assertSame(LibraryTransferRunStatus::Analyzing, $run->fresh()->status);
    }

    public function test_undeclared_traversal_and_corrupt_checksums_are_rejected(): void
    {
        $export = $this->exported(['options']);
        $part = $export->parts()->first();
        $path = Storage::disk('local')->path($part->path);
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('../escape.txt', 'bad');
        $zip->close();
        $this->expectException(RuntimeException::class);
        $this->archive()->inspect($path, true);
    }

    public function test_analysis_and_apply_checkpoint_batches_and_retries_do_not_duplicate_items(): void
    {
        config(['transfers.analysis_batch_records' => 1, 'transfers.apply_batch_items' => 2]);
        Product::factory()->count(3)->create();
        $run = $this->imported($this->exported());
        $count = $run->items()->count();
        $this->assertGreaterThan(20, $count);
        $this->service()->analyze($run);
        $this->assertSame($count, $run->items()->count());
        $this->apply($run);
        $this->assertSame(LibraryTransferRunStatus::Completed, $run->fresh()->status);
        $this->assertSame($count, $run->items()->where('status', 'applied')->count());
        $this->service()->apply($run->fresh());
        $this->assertSame($count, $run->fresh()->processed);
    }

    public function test_invalid_record_is_quarantined_without_blocking_valid_works(): void
    {
        Product::factory()->count(2)->create();
        $export = $this->exported();
        $this->rewriteWorkData($export->parts()->first(), function ($work) {
            $work['dlsite_list']['progress'] = 'Not a valid status';

            return $work;
        });
        $run = $this->imported($export);
        $this->assertSame(1, $run->items()->where('status', 'unavailable')->count());
        $this->assertGreaterThan(1, $run->items()->where('status', 'pending')->count());
        $this->apply($run);
        $this->assertSame(LibraryTransferRunStatus::CompletedWithWarnings, $run->fresh()->status);
    }

    public function test_work_path_and_payload_identity_mismatch_is_a_structural_error(): void
    {
        Product::factory()->create(['id' => 'RJ123456']);
        $export = $this->exported();
        $this->rewriteWorkData($export->parts()->first(), function ($work) {
            $work['japanese']['product_id'] = 'RJ999999';

            return $work;
        });
        $this->expectExceptionMessage('work path and product ID do not match');
        $this->imported($export);
    }

    public static function unsafeArchives(): array
    {
        return array_map(fn($case) => [$case], ['schema', 'checksum', 'size', 'missing', 'duplicate', 'symlink', 'encrypted', 'backslash', 'ratio']);
    }

    #[DataProvider('unsafeArchives')]
    public function test_unsafe_or_damaged_archives_are_rejected(string $case): void
    {
        $export = $this->exported(['options']);
        $path = Storage::disk('local')->path($export->parts()->first()->path);
        $zip = new ZipArchive;
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $entry = $manifest['entries'][0]['path'];
        switch ($case) {
            case 'schema':
                $manifest['schema_version'] = 2;
                break;
            case 'checksum':
                $manifest['entries'][0]['sha256'] = str_repeat('0', 64);
                break;
            case 'size':
                $manifest['entries'][0]['bytes']++;
                break;
            case 'missing':
                $zip->deleteName($entry);
                break;
            case 'duplicate':
                $manifest['entries'][] = $manifest['entries'][0];
                break;
            case 'symlink':
                $zip->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, 0120777 << 16);
                break;
            case 'encrypted':
                $this->assertTrue($zip->setEncryptionName($entry, ZipArchive::EM_AES_256, 'test-secret'));
                break;
            case 'backslash':
                $manifest['entries'][0]['path'] = '..\\options.json';
                break;
            case 'ratio':
                config(['transfers.max_compression_ratio' => 1]);
                break;
        }
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();
        $this->expectException(Exception::class);
        $this->archive()->inspect($path, true);
    }

    public function test_missing_data_cannot_be_bypassed_and_later_reverse_order_uploads_complete(): void
    {
        config(['transfers.fragment_bytes' => 600]);
        Product::factory()->count(3)->create();
        $export = $this->service()->export(['works'], 'all', []);
        $export->update(['settings' => [...$export->settings, 'part_bytes' => 8500]]);
        $this->planExport($export);
        $this->assertSame(3, $export->parts()->count());
        foreach ($export->parts()->get() as $part) {
            $this->archive()->build($part);
            $this->assertLessThanOrEqual(8500, $part->fresh()->bytes);
        }
        $run = $this->service()->startImport();
        $last = $export->parts()->latest('id')->first();
        $upload = $this->service()->upload($run, new UploadedFile(Storage::disk('local')->path($last->path), $last->filename, null, null, true));
        $this->service()->inspect($run->fresh(), $upload->id);
        try {
            $this->service()->action($run->fresh(), LibraryTransferAction::WithoutImages);
            $this->fail('Incomplete data must not be bypassed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unavailable', $exception->getMessage());
        }
        $this->assertFalse($run->fresh()->settings['without_images']);
        foreach ($export->parts()->where('id', '<>', $last->id)->orderByDesc('id')->get() as $part) {
            $upload = $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($part->path), $part->filename, null, null, true));
            $this->service()->inspect($run->fresh(), $upload->id);
        }
        $this->service()->analyze($run->fresh());
        $this->assertSame(LibraryTransferRunStatus::Review, $run->fresh()->status);
    }

    public function test_conflicting_part_and_mixed_set_are_rejected_without_replacing_valid_upload(): void
    {
        $export = $this->exported(['options']);
        $data = $export->parts()->first();
        $run = $this->service()->startImport();
        $part = $this->service()->upload($run, new UploadedFile(Storage::disk('local')->path($data->path), $data->filename, null, null, true));
        $other = $this->exported(['options'])->parts()->first();
        try {
            $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($other->path), $other->filename, null, null, true));
            $this->fail('Mixed sets must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('archive set', $exception->getMessage());
        }
        $this->service()->inspect($run->fresh(), $part->id);
        $originalHash = $part->fresh()->sha256;
        $this->rewriteData($data, fn($records) => []);
        $candidate = $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($data->path), $data->filename, null, null, true));
        $this->service()->inspect($run->fresh(), $candidate->id);
        $this->assertStringContainsString('Different content', $part->fresh()->error);
        $this->assertSame(1, $run->parts()->count());
        $this->assertSame($originalHash, $run->parts()->first()->sha256);
    }

    public function test_corrected_image_part_replaces_only_the_invalid_part_in_the_active_import(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);
        $image = $export->parts()->where('kind', 'images')->firstOrFail();
        $validBytes = Storage::disk('local')->get($image->path);
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($image->path));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['entries'][0]['sha256'] = str_repeat('0', 64);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();

        $run = $this->service()->startImport();
        foreach ($export->parts()->get() as $part) {
            $uploaded = $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($part->path), $part->filename, null, null, true));
            $this->service()->inspect($run->fresh(), $uploaded->id);
        }
        $invalid = $run->parts()->where('kind', 'images')->firstOrFail();
        $this->assertSame(LibraryTransferPartStatus::Invalid, $invalid->status);
        $validDataId = $run->parts()->where('kind', 'data')->firstOrFail()->id;

        Storage::disk('local')->put('corrected-image-part.zip', $validBytes);
        $replacement = $this->service()->upload($run->fresh(), new UploadedFile(
            Storage::disk('local')->path('corrected-image-part.zip'),
            $image->filename,
            null,
            null,
            true,
        ));
        $this->service()->inspect($run->fresh(), $replacement->id, $replacement->candidate['path']);

        $this->assertSame(LibraryTransferPartStatus::Valid, $invalid->fresh()->status);
        $this->assertNull($invalid->fresh()->candidate);
        $this->assertSame($validDataId, $run->parts()->where('kind', 'data')->firstOrFail()->id);
        $this->assertSame(LibraryTransferRunStatus::Analyzing, $run->fresh()->status);
    }

    public function test_manifest_fragment_count_bomb_is_rejected_before_analysis(): void
    {
        $part = $this->exported(['options'])->parts()->first();
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($part->path));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['entries'][0]['fragments'] = PHP_INT_MAX;
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();
        $run = $this->service()->startImport();
        $this->postJson(route('options.transfers.upload', $run), ['archive' => new UploadedFile(Storage::disk('local')->path($part->path), $part->filename, null, null, true)])
            ->assertUnprocessable()->assertJsonPath('message', 'Unsupported or invalid archive layout: invalid data entry.');
        $this->assertSame(0, $run->parts()->count());
    }

    #[DataProvider('invalidFragmentMetadata')]
    public function test_manifest_fragment_metadata_uses_strict_integer_bounds(mixed $fragment, mixed $fragments): void
    {
        $part = $this->exported(['options'])->parts()->firstOrFail();
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($part->path));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['entries'][0]['fragment'] = $fragment;
        $manifest['entries'][0]['fragments'] = $fragments;
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();

        $run = $this->service()->startImport();
        $this->postJson(route('options.transfers.upload', $run), [
            'archive' => new UploadedFile(Storage::disk('local')->path($part->path), $part->filename, null, null, true),
        ])->assertUnprocessable()->assertJsonPath('message', 'Unsupported or invalid archive layout: invalid data entry.');
        $this->assertSame(0, $run->parts()->count());
    }

    public static function invalidFragmentMetadata(): array
    {
        return [
            'numeric fragment string' => ['1', 1],
            'numeric fragments string' => [1, '1'],
            'zero fragment' => [0, 1],
            'zero fragments' => [1, 0],
            'fragment exceeds total' => [2, 1],
        ];
    }

    #[DataProvider('invalidManifestJson')]
    public function test_malformed_manifest_json_returns_validation_errors(string $json): void
    {
        $part = $this->exported(['options'])->parts()->first();
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($part->path));
        $zip->addFromString('manifest.json', $json);
        $zip->close();
        $run = $this->service()->startImport();
        $this->postJson(route('options.transfers.upload', $run), ['archive' => new UploadedFile(Storage::disk('local')->path($part->path), $part->filename, null, null, true)])
            ->assertUnprocessable();
        $this->assertSame(0, $run->parts()->count());
    }

    public static function invalidManifestJson(): array
    {
        return [['null'], ['[]'], ['"not an object"'], ['{broken']];
    }

    public function test_dlsite_work_json_without_project_fields_remains_importable(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'notes' => 'Local-only note']);
        $export = $this->exported();
        $this->rewriteWorkData($export->parts()->firstOrFail(), function (array $work): array {
            unset($work['dlsite_list']);

            return $work;
        });

        $run = $this->imported($export);
        $this->assertSame(LibraryTransferRunStatus::Review, $run->status);
        $this->assertTrue($run->items()->where('section', 'works')->where('category', 'titles')->exists());
        foreach (['notes', 'score', 'progress', 'start_date', 'end_date', 'num_re_listen_times', 're_listen_value', 'priority', 'custom_tags'] as $category) {
            $this->assertFalse($run->items()->where('category', $category)->exists(), $category);
        }
    }

    public function test_analysis_does_not_quarantine_operational_failures(): void
    {
        Product::factory()->create();
        $export = $this->exported();
        $run = $this->service()->startImport();
        $part = $export->parts()->first();
        $upload = $this->service()->upload($run, new UploadedFile(Storage::disk('local')->path($part->path), $part->filename, null, null, true));
        $this->service()->inspect($run->fresh(), $upload->id);
        LibraryImportItem::creating(fn() => throw new RuntimeException('Temporary write failure'));
        try {
            $this->service()->analyze($run->fresh());
            $this->fail('Operational failure must propagate to the worker.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Temporary write failure', $exception->getMessage());
        } finally {
            LibraryImportItem::flushEventListeners();
        }
        $this->assertSame(0, $run->items()->count());
        $this->service()->analyze($run->fresh());
        $this->assertSame(LibraryTransferRunStatus::Review, $run->fresh()->status);
    }

    public function test_more_than_ten_reverse_order_parts_are_all_analyzed(): void
    {
        config(['transfers.fragment_bytes' => 600]);
        Product::factory()->count(12)->create();
        $export = $this->service()->export(['works'], 'all', []);
        $export->update(['settings' => [...$export->settings, 'part_bytes' => 8500]]);
        $this->planExport($export);
        $this->assertSame(LibraryTransferRunStatus::AwaitingConfirmation, $export->fresh()->status);
        $this->service()->action($export->fresh(), LibraryTransferAction::ContinueExport);
        foreach ($export->parts()->get() as $part) {
            $this->archive()->build($part);
        }
        $run = $this->imported($export);
        $this->assertSame(LibraryTransferRunStatus::Review, $run->status);
        $this->assertSame(12, $run->items()->where('section', 'works')->distinct()->count('entity_key'));
    }

    public function test_incorrect_image_layout_returns_a_clear_validation_error(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $hash = $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);
        $part = $export->parts()->where('kind', 'images')->firstOrFail();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($part->path)));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $wrongPath = 'images/RJ123456/cover/' . $hash . '.png';
        $this->assertTrue($zip->renameName($manifest['entries'][0]['path'], $wrongPath));
        $manifest['entries'][0]['path'] = $wrongPath;
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();
        $this->expectExceptionMessage('Unsupported or invalid archive layout: invalid image entry.');
        $this->archive()->inspect(Storage::disk('local')->path($part->path));
    }

    public function test_retry_requeues_all_pending_parts_under_the_new_generation(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);
        $run = $this->service()->startImport();
        foreach ($export->parts()->get() as $part) {
            $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($part->path), $part->filename, null, null, true));
        }
        $first = $run->parts()->firstOrFail();
        (new LibraryTransferJob($run->id, LibraryTransferOperation::Inspect, $first->id, $run->generation, $first->upload_path, $first->validation_token))->failed(new RuntimeException('Temporary storage error'));
        $this->assertSame(LibraryTransferRunStatus::Failed, $run->fresh()->status);
        $this->service()->action($run->fresh(), LibraryTransferAction::Retry);
        $this->assertSame(2, $run->fresh()->generation);
        foreach ($run->parts()->get() as $part) {
            Bus::assertDispatched(LibraryTransferJob::class, fn($job) => $job->operation === LibraryTransferOperation::Inspect && $job->partId === $part->id && $job->generation === 2 && $job->uploadPath === $part->upload_path);
            $part->refresh();
            $this->executeJob(new LibraryTransferJob($run->id, LibraryTransferOperation::Inspect, $part->id, 2, $part->upload_path, $part->validation_token));
        }
        $this->assertSame(LibraryTransferRunStatus::Analyzing, $run->fresh()->status);
    }

    public function test_duplicate_failure_retains_accepted_part_and_ignores_old_candidate_callbacks(): void
    {
        $export = $this->exported(['options']);
        $run = $this->imported($export);
        $source = $export->parts()->firstOrFail();
        $upload = fn() => $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($source->path), $source->filename, null, null, true));
        $part = $upload();
        $part->refresh();
        $job = new LibraryTransferJob($run->id, LibraryTransferOperation::Inspect, $part->id, $run->generation, $part->candidate['path'], $part->validation_token);
        $job->failed(new RuntimeException('Temporary storage error'));
        $this->assertSame(LibraryTransferRunStatus::Review, $run->fresh()->status);
        $this->assertSame(LibraryTransferPartStatus::Valid, $part->fresh()->status);
        $this->assertNull($part->fresh()->candidate);
        $this->assertStringContainsString('accepted part is retained', $part->fresh()->error);
        $second = $upload();
        $job->failed(new RuntimeException('Delayed previous failure'));
        $this->assertSame($second->candidate, $part->fresh()->candidate);
        $this->apply($run);
        $run->refresh();
        Bus::assertDispatched(LibraryTransferJob::class, fn($queued) => $queued->operation === LibraryTransferOperation::Inspect && $queued->partId === $part->id && $queued->generation === $run->generation && $queued->uploadPath === $second->candidate['path']);
        $this->service()->inspect($run, $part->id, $second->candidate['path']);
        $this->assertSame(LibraryTransferRunStatus::Completed, $run->fresh()->status);
        $this->assertNull($part->fresh()->candidate);
    }

    public function test_later_analysis_batches_reuse_database_inventory_without_loading_part_manifests(): void
    {
        config(['transfers.analysis_batch_records' => 1]);
        Product::factory()->count(2)->create();
        $export = $this->exported();
        $run = $this->service()->startImport();
        foreach ($export->parts()->get() as $source) {
            $part = $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($source->path), $source->filename, null, null, true));
            $this->service()->inspect($run->fresh(), $part->id);
        }
        $this->service()->analyze($run->fresh());
        $this->assertNotNull($run->fresh()->settings['analysis_entries']);
        DB::enableQueryLog();
        try {
            $this->service()->analyze($run->fresh());
            $this->assertFalse(collect(DB::getQueryLog())->contains(fn($query) => str_contains($query['query'], 'library_transfer_parts')));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertSame(2, $run->items()->distinct()->count('entity_key'));
        $this->assertSame(2, $run->entries()->where('kind', 'data')->count());
        while ($run->fresh()->status === LibraryTransferRunStatus::Analyzing) {
            $this->service()->analyze($run->fresh());
        }
        $this->assertSame(LibraryTransferRunStatus::Review, $run->fresh()->status);
        $this->assertSame(2, $run->items()->distinct()->count('entity_key'));
    }
}
