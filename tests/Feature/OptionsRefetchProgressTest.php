<?php

namespace Tests\Feature;

use App\Livewire\OptionsRefetchProgress;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Support\Refetch\RefetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OptionsRefetchProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_component_polls_only_while_run_is_active(): void
    {
        $product = Product::factory()->create();
        $run = app(RefetchService::class)->createRun([$product->id], false);

        Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->assertSee('wire:poll.1s="refreshProgress"', false)
            ->assertSee('0 / 1 work processed')
            ->assertSeeInOrder(['Fetched', 'Failed', 'Total'])
            ->assertSee('Cancel Refetch');

        $run->forceFill([
            'status' => RefetchRun::STATUS_CANCELLING,
            'cancelled_at' => now(),
        ])->save();

        Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->assertSee('wire:poll.1s="refreshProgress"', false)
            ->assertSee('Cancelling')
            ->assertDontSee('Cancel Refetch');

        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'completed_at' => now(),
        ])->save();

        Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->assertDontSee('wire:poll.1s="refreshProgress"', false)
            ->assertSee('1 / 1 work processed');
    }

    public function test_progress_component_redirects_when_run_completes_during_poll(): void
    {
        $product = Product::factory()->create();
        $run = app(RefetchService::class)->createRun([$product->id], false);

        $component = Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->call('refreshProgress')
            ->assertNoRedirect();

        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'completed_at' => now(),
        ])->save();

        $component
            ->call('refreshProgress')
            ->assertRedirectToRoute('options.refetch.show', $run);
    }

    public function test_progress_component_redirects_when_cancelled_run_reaches_review(): void
    {
        $product = Product::factory()->create();
        $run = app(RefetchService::class)->createRun([$product->id], false);
        $run->forceFill([
            'status' => RefetchRun::STATUS_CANCELLING,
            'cancelled_at' => now(),
        ])->save();

        $component = Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->call('refreshProgress')
            ->assertNoRedirect();

        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'failed_count' => 1,
            'completed_at' => now(),
        ])->save();

        $component
            ->call('refreshProgress')
            ->assertRedirectToRoute('options.refetch.show', $run);
    }
}
