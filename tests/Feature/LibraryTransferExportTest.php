<?php

namespace Tests\Feature;

use App\Enums\LibraryTransferAction;
use App\Enums\LibraryTransferOperation;
use App\Enums\LibraryTransferRunStatus;
use App\Jobs\LibraryTransferJob;
use App\Livewire\OptionsTransferRun;
use App\Livewire\OptionsTransfers;
use App\Models\Genre;
use App\Models\LibraryTransferRun;
use App\Models\Option;
use App\Models\Product;
use App\Support\Transfers\SourceChanged;
use App\Support\Transfers\TransferArchive;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Concerns\InteractsWithLibraryTransfers;
use Tests\TestCase;
use ZipArchive;

class LibraryTransferExportTest extends TestCase
{
    use InteractsWithLibraryTransfers;

    public static function planningPhases(): array
    {
        return array_map(fn($phase) => [$phase], ['works', 'tag-library', 'options', 'pack-data', 'pack-images', 'normalize-fragments', 'finalize']);
    }

    #[DataProvider('planningPhases')]
    public function test_planning_phase_and_successor_roll_back_together_and_retry(string $phase): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.jpg']);
        Product::factory()->create(['id' => 'RJ123457']);
        Genre::resolveByTitle('Exported tag');
        $this->saveImage('Works/RJ123456/cover.jpg');
        config(['transfers.database_batch_records' => 1]);
        $run = $this->service()->export(['works', 'images', 'tag-library', 'options'], 'all', []);
        $this->archive()->plan($run->fresh());
        while ($run->fresh()->settings['planning']['phase'] !== $phase) {
            $this->assertSame(LibraryTransferRunStatus::Planning, $run->fresh()->status);
            $this->archive()->plan($run->fresh());
        }
        $snapshot = fn() => [
            $run->fresh()->getAttributes(),
            $run->entries()->orderBy('id')->get()->map->getAttributes()->all(),
            $run->parts()->orderBy('id')->get()->map->getAttributes()->all(),
            DB::table('jobs')->count(),
        ];
        $before = $snapshot();
        Bus::swap(Bus::getFacadeRoot()->dispatcher);
        $fail = true;
        DB::listen(function ($query) use (&$fail): void {
            if ($fail && str_starts_with(strtolower($query->sql), 'insert into `jobs`')) {
                $fail = false;
                throw new RuntimeException('Interrupted after successor insertion');
            }
        });
        try {
            $this->archive()->plan($run->fresh());
            $this->fail('The planning batch must be interrupted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Interrupted after successor insertion', $exception->getMessage());
        } finally {
            $fail = false;
        }
        $this->assertSame($before, $snapshot());
        $this->planExport($run);
        $this->assertSame(2, $run->entries()->where('section', 'works')->count());
        $this->assertSame($run->entries()->count(), $run->entries()->distinct()->count('path'));
        foreach ($run->parts()->get() as $part) {
            $this->archive()->build($part);
        }
        $this->assertSame(LibraryTransferRunStatus::Ready, $run->fresh()->status);
    }

    public function test_stale_planning_calls_cannot_mutate_cancelled_or_superseded_runs(): void
    {
        foreach (['cancelled', 'superseded'] as $case) {
            $run = $this->service()->export(['options'], 'all', []);
            $stale = $run->fresh();
            $run->update($case === 'cancelled'
                ? ['status' => LibraryTransferRunStatus::Cancelled]
                : ['generation' => $run->generation + 1]);
            $before = $run->fresh()->getAttributes();
            $this->archive()->plan($stale);
            $this->assertSame($before, $run->fresh()->getAttributes());
            $this->assertSame(0, $run->entries()->count());
            $this->assertSame(0, $run->parts()->count());
        }
    }

    public function test_export_planning_persists_bounded_inventory_before_packing_parts(): void
    {
        config(['transfers.database_batch_records' => 1]);
        Product::factory()->count(2)->create();
        $run = $this->service()->export(['works'], 'all', []);

        $this->archive()->plan($run->fresh());
        $this->assertSame(LibraryTransferRunStatus::Planning, $run->fresh()->status);
        $this->assertSame('works', $run->fresh()->settings['planning']['phase']);
        $this->assertSame(1, $run->entries()->count());
        $this->assertSame(0, $run->parts()->count());

        $this->planExport($run);
        $this->assertNull($run->fresh()->settings['planning']);
        $this->assertSame(2, $run->entries()->where('section', 'works')->count());
        $this->assertGreaterThan(0, $run->parts()->count());
    }

    public function test_simple_work_search_preserves_hidden_selections_and_snapshots_exact_rj_codes(): void
    {
        Product::factory()->create(['id' => 'RJ111111', 'work_name' => 'Alpha']);
        Product::factory()->create(['id' => 'RJ222222', 'work_name' => 'Beta']);
        Livewire::test(OptionsTransfers::class)
            ->set('mode', 'selected')
            ->set('search', 'Alpha')
            ->assertSee('RJ111111')
            ->assertDontSee('RJ222222')
            ->set('selectedIds', ['RJ111111'])
            ->set('search', 'Beta')
            ->assertSet('selectedIds', ['RJ111111'])
            ->assertDontSee('RJ111111')
            ->assertSee('RJ222222')
            ->call('export')->assertHasNoErrors();
        $this->assertSame(['RJ111111'], LibraryTransferRun::first()->settings['product_ids']);
    }

    public function test_export_work_inventory_progress_mentions_images_only_when_selected(): void
    {
        Product::factory()->count(2)->create();
        config(['transfers.analysis_batch_records' => 1]);

        $withoutImages = $this->service()->export(['works'], 'all', []);
        $response = $this->get(route('options.transfers.show', $withoutImages))->assertOk();
        $this->assertMatchesRegularExpression(
            '/<div class="progress-summary">\s*<span role="status">0 of 2 works exported<\/span>/',
            $response->getContent(),
        );
        $this->archive()->plan($withoutImages->fresh());
        $this->assertStringStartsWith('Preparing ', $withoutImages->fresh()->stage);
        $this->assertStringEndsWith(' for export', $withoutImages->fresh()->stage);
        $this->assertStringNotContainsString('images', $withoutImages->fresh()->stage);

        $withImages = $this->service()->export(['works', 'images'], 'all', []);
        $this->archive()->plan($withImages->fresh());
        $this->assertStringContainsString('and its images for export', $withImages->fresh()->stage);
    }

    public function test_data_only_export_confirmation_omits_image_counts(): void
    {
        $run = LibraryTransferRun::create([
            'direction' => 'export',
            'status' => 'awaiting_confirmation',
            'settings' => ['parts' => ['data' => 11, 'images' => 0]],
            'total' => 11,
        ]);
        foreach (range(1, 11) as $number) {
            $run->parts()->create([
                'kind' => 'data',
                'number' => $number,
                'status' => 'pending',
                'path' => $run->directory() . '/' . $number . '.zip',
                'filename' => $number . '.zip',
                'manifest' => [],
            ]);
        }

        $this->get(route('options.transfers.show', $run))
            ->assertOk()
            ->assertSee('This export needs 11 data files')
            ->assertDontSee('11 data, 0 images')
            ->assertDontSee('Images: 0 / 0');
    }

    public function test_export_warnings_identify_the_incomplete_image_position(): void
    {
        Product::factory()->create([
            'id' => 'RJ123456',
            'work_image' => 'storage/Works/RJ123456/missing-cover.png',
            'sample_images' => [
                'storage/Works/RJ123456/sample_1.png',
                'storage/Works/RJ123456/missing-sample.png',
            ],
        ]);
        $this->saveImage('Works/RJ123456/sample_1.png');

        $export = $this->exported(['works', 'images']);

        $this->assertSame([
            'RJ123456: cover image is incomplete.',
            'RJ123456: sample image 2 is incomplete.',
        ], $export->warnings);
    }

    public function test_fragment_boundaries_part_numbering_and_more_than_ten_confirmation(): void
    {
        $archive = $this->archive();
        $file = ['path' => 'works.json', 'bytes' => 100];
        $limit = $archive->bound([$file]);
        $this->assertGreaterThan(100, $limit);
        $this->assertSame('dlsite-list_20260909_154327Z.data.part001-of-100.zip', TransferArchive::filename('2026-09-09T15:43:27Z', 'data', 1, 100));
        config(['transfers.fragment_bytes' => 600]);
        Product::factory()->count(11)->create();
        $run = $this->service()->export(['works'], 'all', []);
        $run->update(['settings' => [...$run->settings, 'part_bytes' => 8500]]);
        do {
            $archive->plan($run->fresh());
        } while ($run->fresh()->status === LibraryTransferRunStatus::Planning);
        $this->assertSame(LibraryTransferRunStatus::AwaitingConfirmation, $run->fresh()->status);
        $this->assertGreaterThan(10, $run->fresh()->total);
    }

    public function test_changed_export_source_invalidates_plan_before_publishing(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $this->saveImage('Works/RJ123456/cover.png');
        $run = $this->service()->export(['works', 'images'], 'all', []);
        $this->planExport($run);
        $this->saveImage('Works/RJ123456/cover.png', 30);
        $part = $run->parts()->where('kind', 'images')->first();
        $run->refresh();
        $job = new LibraryTransferJob($run->id, LibraryTransferOperation::Build, $part->id, $run->generation, token: $run->job_token);
        $this->executeJob($job);
        $this->assertSame(LibraryTransferRunStatus::Queued, $run->fresh()->status);
        $this->assertSame(1, $run->fresh()->settings['replans']);
        $this->assertFalse(Storage::disk('local')->exists((string) $part->path));
    }

    public function test_ready_export_can_start_every_part_download_from_one_button(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);

        $response = $this->get(route('options.transfers.show', $export))->assertOk()
            ->assertSee('Download all')
            ->assertSee('data-transfer-downloads', false)
            ->assertSee('data-started="Choose a folder for the archive files."', false)
            ->assertSee('data-download-all', false)
            ->assertSee('class="transfer-parts__heading"', false);

        $this->assertSame(
            $export->parts()->count(),
            substr_count($response->getContent(), 'data-download-part'),
        );

        $export->update(['status' => 'building']);
        $this->get(route('options.transfers.show', $export->fresh()))
            ->assertOk()
            ->assertDontSee('data-download-all', false);
    }

    public function test_export_progress_reports_completed_works_instead_of_the_current_archive_part(): void
    {
        $run = LibraryTransferRun::create([
            'direction' => 'export',
            'status' => 'building',
            'stage' => 'Building data part 2',
            'settings' => [
                'scopes' => ['works'],
                'product_ids' => ['RJ111111', 'RJ222222'],
                'planning' => null,
                'parts' => ['data' => 2, 'images' => 0],
            ],
            'processed' => 1,
            'total' => 2,
        ]);
        $ready = $run->parts()->create([
            'kind' => 'data',
            'number' => 1,
            'status' => 'ready',
            'filename' => 'part-1.zip',
            'bytes' => 1,
            'manifest' => [],
        ]);
        $pending = $run->parts()->create([
            'kind' => 'data',
            'number' => 2,
            'status' => 'pending',
            'filename' => 'part-2.zip',
            'bytes' => 1,
            'manifest' => [],
        ]);
        foreach ([[$ready, 'RJ111111'], [$pending, 'RJ222222']] as $position => [$part, $code]) {
            $run->entries()->create([
                'library_transfer_part_id' => $part->id,
                'generation' => 1,
                'kind' => 'data',
                'section' => 'works',
                'logical_key' => $code,
                'position' => $position,
                'path' => 'works/' . $code . '/work.json',
                'source_disk' => 'local',
                'source_path' => 'Transfers/' . $run->id . '/' . $code . '.json',
                'bytes' => 1,
                'sha256' => str_repeat('a', 64),
                'media_type' => 'application/json',
            ]);
        }

        $this->get(route('options.transfers.show', $run))
            ->assertOk()
            ->assertSee('1 of 2 works exported')
            ->assertSee('aria-valuenow="50"', false);
    }

    public function test_non_work_export_stage_is_shown_in_the_progress_summary(): void
    {
        $run = LibraryTransferRun::create([
            'direction' => 'export',
            'status' => 'planning',
            'stage' => 'Preparing options inventory',
            'settings' => ['scopes' => ['options']],
            'processed' => 1,
            'total' => 3,
        ]);

        $response = $this->get(route('options.transfers.show', $run))->assertOk();
        $this->assertMatchesRegularExpression(
            '/<div class="progress-summary">\s*<span role="status">Preparing options inventory<\/span>/',
            $response->getContent(),
        );
    }

    public function test_export_size_resets_but_authentication_theme_remains_local_setting(): void
    {
        Option::setExportPartMib(8192);
        Option::setAuthenticationPageTheme('black');
        Option::resetVisibleSettingsToDefault();
        $this->assertSame(256, Option::exportPartMib());
        $this->assertSame('black', Option::authenticationPageTheme());
    }

    public function test_irrelevant_deleted_selections_do_not_block_export(): void
    {
        $this->assertSame([], $this->service()->export(['options'], 'selected', ['RJ999999'])->settings['product_ids']);
        Product::factory()->create(['id' => 'RJ123456']);
        $this->assertSame(['RJ123456'], $this->service()->export(['works'], 'all', ['RJ999999'])->settings['product_ids']);
    }

    public function test_new_exports_and_saved_images_use_standard_names(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ123456',
            'work_image' => 'storage/Works/RJ123456/cover.png',
            'sample_images' => ['storage/Works/RJ123456/sample_2.jpeg'],
            'age_category' => 'R18'
        ]);
        Product::factory()->create(['id' => 'RJ000002']);
        $this->saveImage('Works/RJ123456/cover.png');
        $this->saveImage('Works/RJ123456/sample_2.jpeg');
        $export = $this->exported(['works', 'images']);
        $data = $export->parts()->where('kind', 'data')->firstOrFail();
        $this->assertSame('dlsite-async+dlsite-list', $data->manifest['work_entry_format']);
        $this->assertSame(
            ['works/RJ123456/work.json', 'works/RJ000002/work.json'],
            array_column($data->manifest['entries'], 'path'),
        );
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($data->path)));
        $work = json_decode($zip->getFromName('works/RJ123456/work.json'), true);
        $zip->close();
        $this->assertSame(['japanese', 'english', 'dlsite_list'], array_keys($work));
        $this->assertSame([
            'product_id',
            'site_id',
            'maker_id',
            'work_name',
            'age_category',
            'circle',
            'brand',
            'publisher',
            'work_image',
            'regist_date',
            'work_type',
            'book_type',
            'announce_date',
            'modified_date',
            'scenario',
            'illustration',
            'voice_actor',
            'author',
            'music',
            'writer',
            'genre',
            'label',
            'event',
            'file_format',
            'file_size',
            'language',
            'page_count',
            'description',
            'sample_images',
            'work_name_masked',
            'title_name',
            'title_name_masked',
        ], array_keys($work['japanese']));
        $this->assertSame(array_keys($work['japanese']), array_keys($work['english']));
        $this->assertSame([
            'notes',
            'score',
            'progress',
            'start_date',
            'end_date',
            'num_re_listen_times',
            're_listen_value',
            'priority',
            'custom_tags',
            'cover',
            'sample_images',
            'created_at',
            'updated_at',
        ], array_keys($work['dlsite_list']));
        $this->assertSame(['_value_', '_name_'], array_keys($work['japanese']['age_category']));
        $this->assertSame([3, 'R18'], array_values($work['japanese']['age_category']));
        $this->assertSame('works/RJ123456/images/cover.png', $work['japanese']['work_image']);
        $this->assertSame(['works/RJ123456/images/sample_1.jpeg'], $work['japanese']['sample_images']);
        $this->assertStringNotContainsString('storage/', json_encode($work, JSON_THROW_ON_ERROR));
        $paths = array_column($export->parts()->where('kind', 'images')->first()->manifest['entries'], 'path');
        $this->assertSame(['works/RJ123456/images/cover.png', 'works/RJ123456/images/sample_1.jpeg'], $paths);
        $product->update(['work_image' => null, 'sample_images' => []]);
        $run = $this->imported($export);
        $this->apply($run);
        $this->assertSame('storage/Works/RJ123456/cover.png', $product->fresh()->work_image);
        $this->assertSame(['storage/Works/RJ123456/sample_1.jpeg'], $product->fresh()->sample_images);
    }

    public function test_retry_after_repeated_source_changes_restarts_planning_from_current_images(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $this->saveImage('Works/RJ123456/cover.png');
        $run = $this->service()->export(['works', 'images'], 'all', []);
        $this->planExport($run);
        $run->refresh()->update(['settings' => [...$run->settings, 'replans' => 2]]);
        $part = $run->parts()->where('kind', 'images')->firstOrFail();
        $expectedHash = $this->saveImage('Works/RJ123456/cover.png', 25);
        $run->refresh();
        $job = new LibraryTransferJob($run->id, LibraryTransferOperation::Build, $part->id, $run->generation, token: $run->job_token);
        try {
            $this->executeJob($job);
            $this->fail('Repeated source changes should pause for a manual retry.');
        } catch (SourceChanged $exception) {
            $job->failed($exception);
        }
        $this->assertSame('plan', $run->fresh()->settings['retry_operation']);
        $this->service()->action($run->fresh(), LibraryTransferAction::Retry);
        $this->assertSame(LibraryTransferRunStatus::Queued, $run->fresh()->status);
        $this->planExport($run);
        $newPart = $run->parts()->where('kind', 'images')->firstOrFail();
        $this->assertSame($expectedHash, $newPart->manifest['entries'][0]['sha256']);
    }
}
