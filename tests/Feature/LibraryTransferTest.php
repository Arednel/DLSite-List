<?php

namespace Tests\Feature;

use App\Enums\LibraryImportDecision;
use App\Enums\LibraryImportItemStatus;
use App\Enums\LibraryTransferAction;
use App\Enums\LibraryTransferDirection;
use App\Enums\LibraryTransferOperation;
use App\Enums\LibraryTransferPartStatus;
use App\Enums\LibraryTransferRunStatus;
use App\Livewire\OptionsTransferRun;
use App\Models\LibraryImportItem;
use App\Models\LibraryTransferRun;
use App\Models\Option;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Support\Transfers\ImportValue;
use App\Support\Transfers\PortableOptions;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Livewire\Livewire;
use ReflectionClass;
use Tests\Feature\Concerns\InteractsWithLibraryTransfers;
use Tests\TestCase;

class LibraryTransferTest extends TestCase
{
    use InteractsWithLibraryTransfers;

    public function test_transfer_enums_roundtrip_existing_database_values(): void
    {
        $run = LibraryTransferRun::create([
            'direction' => LibraryTransferDirection::Export,
            'status' => LibraryTransferRunStatus::Queued,
            'settings' => [],
        ]);
        foreach (LibraryTransferDirection::cases() as $direction) {
            $run->update(['direction' => $direction]);
            $this->assertSame($direction, $run->fresh()->direction);
            $this->assertDatabaseHas('library_transfer_runs', ['id' => $run->id, 'direction' => $direction->value]);
        }
        foreach (LibraryTransferRunStatus::cases() as $status) {
            $run->update(['status' => $status]);
            $this->assertSame($status, $run->fresh()->status);
            $this->assertDatabaseHas('library_transfer_runs', ['id' => $run->id, 'status' => $status->value]);
        }

        $part = $run->parts()->create(['kind' => 'data', 'number' => 1, 'filename' => 'part.zip', 'manifest' => []]);
        foreach (LibraryTransferPartStatus::cases() as $status) {
            $part->update(['status' => $status]);
            $this->assertSame($status, $part->fresh()->status);
            $this->assertDatabaseHas('library_transfer_parts', ['id' => $part->id, 'status' => $status->value]);
        }

        $item = $run->items()->create([
            'section' => 'options',
            'category' => 'options',
            'entity_key' => Option::UI_LANGUAGE,
            'identity_hash' => str_repeat('a', 64),
        ]);
        foreach (LibraryImportItemStatus::cases() as $status) {
            $item->update(['status' => $status]);
            $this->assertSame($status, $item->fresh()->status);
            $this->assertDatabaseHas('library_import_items', ['id' => $item->id, 'status' => $status->value]);
        }
        foreach (LibraryImportDecision::cases() as $decision) {
            $item->update(['decision' => $decision]);
            $this->assertSame($decision, $item->fresh()->decision);
            $this->assertDatabaseHas('library_import_items', ['id' => $item->id, 'decision' => $decision->value]);
        }

        foreach ([...LibraryTransferAction::cases(), ...LibraryTransferOperation::cases()] as $enum) {
            $this->assertSame($enum, $enum::from($enum->value));
        }
        $run->update(['settings' => ['retry_operation' => 'analyze', 'retry_status' => 'analyzing']]);
        $this->assertSame(LibraryTransferOperation::Analyze, $run->retryOperation());
        $this->assertSame(LibraryTransferRunStatus::Analyzing, $run->retryStatus());
    }

    public function test_option_defaults_are_individually_resolved_and_configuration_remains_dynamic(): void
    {
        foreach (Option::defaults() as $key => $default) {
            $this->assertSame($default, Option::defaultFor($key), $key);
        }

        $original = config('transfers.default_part_mib');
        config(['transfers.default_part_mib' => 321]);
        $this->assertSame(321, Option::defaultFor(Option::EXPORT_PART_MIB));
        config(['transfers.default_part_mib' => $original]);

        $this->expectException(InvalidArgumentException::class);
        Option::defaultFor('unknown-option');
    }

    public function test_import_image_preview_paths_do_not_persist_the_application_host(): void
    {
        config(['app.url' => 'http://192.168.1.1:8080']);

        $preview = ImportValue::previewValue(
            ['files' => [['entry_id' => 8443]]],
            'cover',
            'cover',
            5,
        );

        $this->assertSame(['/options/transfers/5/images/8443'], $preview['value']);
        $encoded = json_encode($preview, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('localhost', $encoded);
        $this->assertStringNotContainsString('192.168.1.1', $encoded);
    }

    public function test_import_previews_show_all_images_and_array_items(): void
    {
        $files = array_map(
            fn(int $entryId): array => ['entry_id' => $entryId],
            range(1, 12),
        );
        $images = ImportValue::previewValue(['files' => $files], 'sample_images', 'sample_images', 5);

        $this->assertCount(12, $images['value']);
        $this->assertFalse($images['truncated']);

        $values = array_map(fn(int $number): string => "Value {$number}", range(1, 75));
        $preview = ImportValue::previewValue($values, 'custom_tags', 'custom_tags', 5);

        $this->assertCount(75, $preview['value']);
        $this->assertFalse($preview['truncated']);
    }

    public function test_item_and_value_preview_paths_are_identical(): void
    {
        $item = new LibraryImportItem([
            'library_transfer_run_id' => 42,
            'category' => 'titles',
            'entity_key' => 'RJ123456',
            'metadata' => ['field' => 'work_name'],
            'incoming' => 'Imported title',
        ]);

        $this->assertSame(
            ImportValue::preview($item, 'incoming'),
            ImportValue::previewValue('Imported title', 'titles', 'work_name', 42),
        );
    }

    public function test_options_registry_roundtrips_effective_values_and_excludes_security_and_local_size(): void
    {
        $options = app(PortableOptions::class);
        foreach ($options->all() as $key => $value) {
            $options->validate($key, $value);
            $options->apply($key, $value);
            $this->assertSame($value, $options->current($key), $key);
        }
        $this->assertArrayNotHasKey(Option::EXPORT_PART_MIB, $options->all());
        $this->assertArrayNotHasKey('authentication_enabled', $options->all());
        $this->assertSame($options->defaults(), $options->all());
        $normal = array_values(array_diff((new ReflectionClass(Option::class))->getConstant('RESETTABLE_OPTIONS'), [Option::EXPORT_PART_MIB]));
        $normal[] = Option::AUTHENTICATION_PAGE_THEME;
        $portable = array_keys($options->all());
        sort($normal);
        sort($portable);
        $this->assertSame($normal, $portable, 'Every normal UI option needs an explicit portable classification.');
    }

    public function test_import_item_errors_use_the_collapsed_error_list(): void
    {
        Product::factory()->create(['id' => 'RJ123456']);
        $export = $this->exported(['works']);
        $run = $this->imported($export);
        $item = $run->items()->firstOrFail();
        $item->update(['error' => 'Imported value is invalid.']);

        Livewire::test(OptionsTransferRun::class, ['run' => $run->fresh()])
            ->assertSee('Errors (1)')
            ->assertSee("{$item->entity_key}: Imported value is invalid.")
            ->assertSee('<details class="review-errors">', false)
            ->assertHasNoErrors();
    }

    public function test_options_page_and_run_review_render_with_refetch_choice_labels(): void
    {
        Product::factory()->create(['id' => 'RJ123456']);
        $this->get(route('options.index', ['tab' => 'transfers']))->assertOk()
            ->assertSee('Import / Export')
            ->assertSee('css/options.css', false)
            ->assertSee('css/transfers.css', false);
        $export = $this->exported(['works', 'options']);
        $this->assertSame(LibraryTransferRunStatus::Ready, $export->status);
        $export->update(['warnings' => ['RJ123456: sample image 2 is incomplete.']]);
        Livewire::test(OptionsTransferRun::class, ['run' => $export->fresh()])
            ->assertSee('Errors (1)')
            ->assertSee('RJ123456: sample image 2 is incomplete.')
            ->assertHasNoErrors();
        $this->get(route('options.transfers.show', $export))->assertOk()
            ->assertSee($export->parts()->first()->filename)
            ->assertSee('Errors (1)')
            ->assertSee('RJ123456: sample image 2 is incomplete.')
            ->assertSee('<details class="review-errors">', false)
            ->assertDontSee('<div class="notice" role="status">RJ123456: sample image 2 is incomplete.</div>', false)
            ->assertSee('css/options.css', false)
            ->assertSee('css/transfers.css', false)
            ->assertSee('css/title-tooltips.css', false)
            ->assertSee('scripts/title-tooltips.js', false)
            ->assertSee('class="progress-summary"', false)
            ->assertSee('class="progress-track"', false)
            ->assertSee('class="progress-fill" style="width: 100%"', false);
        $run = $this->imported($export);
        $this->assertSame(LibraryTransferRunStatus::Review, $run->status);
        $this->assertFalse($run->items()->where('decision', '<>', 'ignore')->exists());
        $this->get(route('options.transfers.show', $run))->assertOk()
            ->assertSee('Review changes')
            ->assertSee('aria-label="About Ignore"', false)
            ->assertSee('aria-label="About Overwrite"', false)
            ->assertSee('aria-label="About Merge"', false)
            ->assertSee('aria-label="About Refresh conflicts"', false)
            ->assertSee('title="Rechecks failed and conflicting changes against current local data, then resets them to Ignore."', false)
            ->assertSee('title="Merge fills empty work fields and adds imported tags, contributors, and images without removing existing ones. For Options, keys absent from the archive keep their current values."', false)
            ->assertSee('Tab choice')
            ->assertSee('Use tab choice')
            ->assertSee('class="refetch-tab-panel"', false)
            ->assertSee('class="refetch-tab-header"', false)
            ->assertSee('class="refetch-change-comparison"', false)
            ->assertSee('Setting all review choices to Ignore...')
            ->assertSee('Setting all review choices to Merge...')
            ->assertSee('Setting all review choices to Overwrite...')
            ->assertSee('wire:loading', false)
            ->assertSee("wire:target=\"decideMany('ignore', true)\"", false)
            ->assertSee("wire:target=\"decideMany('merge', true)\"", false)
            ->assertSee("wire:target=\"decideMany('overwrite', true)\"", false)
            ->assertSeeInOrder(['Apply Tab', 'Cancel Import', 'Clean up this Import']);
        Livewire::test(OptionsTransferRun::class, ['run' => $run])
            ->call('ask', 'apply')
            ->assertSee('Apply and resolve this tab?')
            ->assertSee('Applying tab...')
            ->assertSee('wire:loading', false)
            ->assertSee('wire:target="confirm"', false)
            ->call('cancelConfirmation')
            ->call('ask', 'cancel')
            ->assertSee('Cancel this Import? Already applied changes are retained.')
            ->assertSee('Cancelling transfer...')
            ->call('cancelConfirmation')
            ->call('tab', 'works', 'titles')->call('decideMany', 'merge')
            ->assertHasNoErrors();
        $this->assertSame(2, $run->items()->where('decision', 'merge')->count());
        $ignored = $run->items()->where('decision', 'ignore')->firstOrFail();
        $this->review()->applyItem($ignored);
        $this->assertSame(LibraryImportItemStatus::Ignored, $ignored->fresh()->status);
        $databaseDefault = $run->items()->create([
            'section' => 'options',
            'category' => 'options',
            'entity_key' => 'database-default',
            'identity_hash' => str_repeat('f', 64),
        ]);
        $this->assertSame(LibraryImportDecision::Ignore, $databaseDefault->fresh()->decision);
    }

    public function test_new_work_is_an_atomic_add_and_reimport_in_new_run_is_allowed(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ123456',
            'work_name' => 'Imported title',
            'description' => 'Imported description',
            'notes' => 'Imported notes',
            'work_image' => 'storage/Works/RJ123456/cover.png',
            'created_at' => '2020-01-02 03:04:05',
            'updated_at' => '2021-01-02 03:04:05',
        ]);
        Product::factory()->create(['id' => 'RJ123455', 'work_name' => 'Existing title']);
        $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);
        $product->delete();
        $run = $this->imported($export);
        $this->assertSame(1, $run->items()->where('category', 'new_works')->count());
        $imageEntry = $run->entries()->where('kind', 'images')->firstOrFail();
        $this->get(route('options.transfers.image', [$run, $imageEntry]))->assertOk();
        $unrelatedRun = LibraryTransferRun::create(['direction' => 'import', 'status' => 'waiting_for_parts', 'settings' => []]);
        $this->get(route('options.transfers.image', [$unrelatedRun, $imageEntry]))->assertNotFound();
        $view = Livewire::test(OptionsTransferRun::class, ['run' => $run])
            ->assertSeeInOrder(['New works', 'Works'])
            ->assertSee('Apply All New Works Tabs')
            ->assertSee('New work import')
            ->assertSee('No existing local work')
            ->assertSee('Imported title')
            ->assertSeeInOrder(['Titles', 'Descriptions', 'Series', 'Age', 'Circle', 'Maker ID'])
            ->call('mainTab', 'works')
            ->assertSet('section', 'works')
            ->assertSet('category', 'titles')
            ->assertSee('Existing title')
            ->call('mainTab', 'new_works')
            ->assertSet('category', 'new_works')
            ->assertSee('Imported title')
            ->call('newWorkTab', 'descriptions')->assertSee('Imported description')
            ->call('newWorkTab', 'notes')->assertSee('Imported notes')
            ->call('newWorkTab', 'cover')->assertSee('class="refetch-preview-image"', false)
            ->call('decideMany', 'overwrite')->assertHasNoErrors();
        $newWork = $run->items()->where('category', 'new_works')->firstOrFail();
        $this->assertSame(LibraryImportDecision::Merge, $newWork->fresh()->decision);
        $view->call('decideMany', 'overwrite', true)->assertHasNoErrors();
        $this->assertSame(LibraryImportDecision::Merge, $newWork->fresh()->decision);
        $this->assertSame(LibraryImportDecision::Overwrite, $run->items()->where('entity_key', 'RJ123455')->firstOrFail()->decision);
        $view->call('ask', 'apply')->call('confirm');
        while ($run->fresh()->status === LibraryTransferRunStatus::Applying) {
            $this->service()->apply($run->fresh());
        }
        $view->call('$refresh')
            ->assertSet('section', 'works')
            ->assertSet('category', 'titles')
            ->assertSee('Existing title');
        $this->apply($run, 'merge');
        $this->assertDatabaseHas('products', ['id' => 'RJ123456', 'created_at' => '2020-01-02 03:04:05', 'updated_at' => '2021-01-02 03:04:05']);
        $this->assertSame(LibraryTransferRunStatus::Review, $this->imported($export)->status);
    }

    public function test_import_review_orders_new_works_before_existing_works_and_keeps_other_main_tabs(): void
    {
        $newProduct = Product::factory()->create(['id' => 'RJ123456', 'work_name' => 'New work title']);
        Product::factory()->create(['id' => 'RJ123455', 'work_name' => 'Existing work title']);
        $export = $this->exported(['works', 'tag-library', 'options']);
        $newProduct->delete();

        $run = $this->imported($export);
        $view = Livewire::test(OptionsTransferRun::class, ['run' => $run])
            ->assertSeeInOrder(['New works', 'Works', 'Tag Library', 'Options'])
            ->call('mainTab', 'works')
            ->assertSet('category', 'titles')
            ->assertSee('Existing work title')
            ->call('mainTab', 'new_works')
            ->assertSet('category', 'new_works')
            ->assertSee('New work title');

        $view->call('newWorkTab', 'descriptions')->assertHasNoErrors();
    }

    public function test_downloads_are_controlled_and_shared_refetch_lock_blocks_apply(): void
    {
        $export = $this->exported(['options']);
        $part = $export->parts()->first();
        $this->get(route('options.transfers.download', [$export, $part]))->assertDownload($part->filename);
        $other = $this->service()->startImport();
        $this->get(route('options.transfers.download', [$other, $part]))->assertNotFound();
        $run = $this->imported($export);
        $this->service()->action($run, LibraryTransferAction::Apply);
        $lock = Cache::lock(RefetchRun::LIFECYCLE_LOCK, 3600);
        $lock->get();
        try {
            $this->expectException(LockTimeoutException::class);
            $this->service()->apply($run->fresh());
        } finally {
            $lock->release();
        }
    }
}
