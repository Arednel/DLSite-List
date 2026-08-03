<?php

namespace Tests\Feature;

use App\Livewire\OptionsRefetchActions;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Support\Refetch\RefetchCleanupService;
use App\Support\Refetch\RefetchService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OptionsRefetchCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_requires_confirmation_and_removes_only_refetch_data(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $product = Product::factory()->create();
        $run = app(RefetchService::class)->createRun([$product->id], true);
        $run->forceFill(['status' => RefetchRun::STATUS_APPLIED])->save();

        Storage::disk('local')->put("Refetch/{$run->id}/Works/{$product->id}.json", '{"staged":true}');
        Storage::disk('local')->put('Refetch/root.json', '{"orphaned":true}');
        Storage::disk('public')->put("Refetch/{$run->id}/Works/{$product->id}/cover.jpg", 'staged-cover');
        Storage::disk('local')->put("Works/{$product->id}.json", '{"canonical":true}');
        Storage::disk('public')->put("Works/{$product->id}/cover.jpg", 'canonical-cover');

        $component = Livewire::test(OptionsRefetchActions::class)
            ->assertSee('Go to latest refetch')
            ->assertSee('Clean up refetch data');

        $component
            ->call('cleanup')
            ->assertSet('confirmingCleanup', false);

        $this->assertDatabaseCount('refetch_runs', 1);
        $this->assertDatabaseCount('refetch_work_results', 1);

        $component
            ->call('askCleanup')
            ->assertSet('confirmingCleanup', true)
            ->assertSee('Permanently delete all refetch run records and all downloaded refetch images?')
            ->assertSee('id="refetch-cleanup-modal-title"', false);

        $this->assertDatabaseCount('refetch_runs', 1);
        $this->assertDatabaseCount('refetch_work_results', 1);
        $this->assertFileExists(
            Storage::disk('local')->path("Refetch/{$run->id}/Works/{$product->id}.json")
        );
        $this->assertFileExists(
            Storage::disk('public')->path("Refetch/{$run->id}/Works/{$product->id}/cover.jpg")
        );

        $component
            ->call('cancelCleanup')
            ->assertSet('confirmingCleanup', false)
            ->call('askCleanup')
            ->call('cleanup')
            ->assertSet('confirmingCleanup', false)
            ->assertSet('notice', 'Refetch data cleaned up.')
            ->assertDontSee('Go to latest refetch');

        $this->assertDatabaseCount('refetch_runs', 0);
        $this->assertDatabaseCount('refetch_work_results', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('Refetch'));
        $this->assertSame([], Storage::disk('public')->allFiles('Refetch'));
        $this->assertSame([], Storage::disk('local')->directories('Refetch'));
        $this->assertSame([], Storage::disk('public')->directories('Refetch'));
        $this->assertDirectoryExists(Storage::disk('local')->path('Refetch'));
        $this->assertDirectoryExists(Storage::disk('public')->path('Refetch'));
        $this->assertFileExists(Storage::disk('local')->path("Works/{$product->id}.json"));
        $this->assertFileExists(Storage::disk('public')->path("Works/{$product->id}/cover.jpg"));
    }

    public function test_cleanup_remains_available_when_no_refetch_data_exists(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        Livewire::test(OptionsRefetchActions::class)
            ->assertSee('data-refetch-cleanup-unavailable="false"', false)
            ->call('askCleanup')
            ->assertSet('confirmingCleanup', true)
            ->call('cleanup')
            ->assertHasNoErrors()
            ->assertSet('notice', 'Refetch data cleaned up.');
    }

    #[DataProvider('activeStatusProvider')]
    public function test_cleanup_is_unavailable_while_a_run_is_active(string $status): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $product = Product::factory()->create();
        $run = app(RefetchService::class)->createRun([$product->id], false);
        $run->forceFill(['status' => $status])->save();
        Storage::disk('local')->put("Refetch/{$run->id}/Works/{$product->id}.json", '{"staged":true}');

        Livewire::test(OptionsRefetchActions::class)
            ->assertSee('Clean up refetch data')
            ->assertSee('data-refetch-cleanup-unavailable="true"', false)
            ->call('askCleanup')
            ->assertSet('confirmingCleanup', false)
            ->assertHasErrors(['cleanup'])
            ->assertSee(RefetchCleanupService::UNAVAILABLE_MESSAGE);

        $this->assertDatabaseCount('refetch_runs', 1);
        $this->assertDatabaseCount('refetch_work_results', 1);
        $this->assertFileExists(
            Storage::disk('local')->path("Refetch/{$run->id}/Works/{$product->id}.json")
        );
    }

    public function test_cleanup_rechecks_active_runs_after_confirmation(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $product = Product::factory()->create();
        $run = app(RefetchService::class)->createRun([$product->id], false);
        $run->forceFill(['status' => RefetchRun::STATUS_REVIEW])->save();
        Storage::disk('local')->put("Refetch/{$run->id}/Works/{$product->id}.json", '{"staged":true}');

        $component = Livewire::test(OptionsRefetchActions::class)
            ->call('askCleanup')
            ->assertSet('confirmingCleanup', true);

        $run->forceFill(['status' => RefetchRun::STATUS_RUNNING])->save();

        $component
            ->call('cleanup')
            ->assertSet('confirmingCleanup', false)
            ->assertHasErrors(['cleanup'])
            ->assertSee(RefetchCleanupService::UNAVAILABLE_MESSAGE);

        $this->assertDatabaseCount('refetch_runs', 1);
        $this->assertDatabaseCount('refetch_work_results', 1);
        $this->assertFileExists(
            Storage::disk('local')->path("Refetch/{$run->id}/Works/{$product->id}.json")
        );
    }

    public function test_cleanup_does_not_overlap_refetch_run_creation(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $lock = Cache::lock(
            RefetchRun::LIFECYCLE_LOCK,
            RefetchRun::LIFECYCLE_LOCK_SECONDS,
        );
        $this->assertTrue($lock->get());

        try {
            Livewire::test(OptionsRefetchActions::class)
                ->call('askCleanup')
                ->assertSet('confirmingCleanup', true)
                ->call('cleanup')
                ->assertSet('confirmingCleanup', false)
                ->assertHasErrors('cleanup')
                ->assertSee(RefetchCleanupService::BUSY_MESSAGE);
        } finally {
            $lock->release();
        }
    }

    public function test_filesystem_failure_after_database_cleanup_leaves_no_stale_run_records(): void
    {
        $product = Product::factory()->create();
        $run = app(RefetchService::class)->createRun([$product->id], false);
        $run->forceFill(['status' => RefetchRun::STATUS_APPLIED])->save();
        $localDisk = Mockery::mock(FilesystemAdapter::class);
        $localDisk->shouldReceive('deleteDirectory')->once()->with('Refetch')->andReturnTrue();
        $localDisk->shouldReceive('makeDirectory')->once()->with('Refetch')->andReturnTrue();
        $publicDisk = Mockery::mock(FilesystemAdapter::class);
        $publicDisk->shouldReceive('deleteDirectory')->once()->with('Refetch')->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($localDisk);
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($publicDisk);

        try {
            app(RefetchCleanupService::class)->cleanup();
            $this->fail('Expected refetch filesystem cleanup to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(RefetchCleanupService::FAILED_MESSAGE, $exception->getMessage());
        }

        $this->assertDatabaseCount('refetch_runs', 0);
        $this->assertDatabaseCount('refetch_work_results', 0);
    }

    public static function activeStatusProvider(): iterable
    {
        yield 'running' => [RefetchRun::STATUS_RUNNING];
        yield 'cancelling' => [RefetchRun::STATUS_CANCELLING];
    }
}
