<?php

namespace Tests\Feature;

use App\Livewire\OptionsRefetchProgress;
use App\Models\Product;
use App\Models\TagRefetchRun;
use App\Support\TagRefetch\TagRefetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OptionsRefetchProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_component_polls_only_while_run_is_active(): void
    {
        $product = Product::factory()->create();
        $run = app(TagRefetchService::class)->createRun([$product->id]);

        Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->assertSee('wire:poll.1s="refreshProgress"', false)
            ->assertSee('0 / 1 work processed')
            ->assertSee('Cancel Refetch');

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_CANCELLING,
            'cancelled_at' => now(),
        ])->save();

        Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->assertSee('wire:poll.1s="refreshProgress"', false)
            ->assertSee('Cancelling')
            ->assertDontSee('Cancel Refetch');

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_REVIEW,
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
        $run = app(TagRefetchService::class)->createRun([$product->id]);

        $component = Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->call('refreshProgress')
            ->assertNoRedirect();

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'completed_at' => now(),
        ])->save();

        $component
            ->call('refreshProgress')
            ->assertRedirectToRoute('options.refetch-tags.show', $run);
    }

    public function test_progress_component_redirects_when_cancelled_run_reaches_review(): void
    {
        $product = Product::factory()->create();
        $run = app(TagRefetchService::class)->createRun([$product->id]);
        $run->forceFill([
            'status' => TagRefetchRun::STATUS_CANCELLING,
            'cancelled_at' => now(),
        ])->save();

        $component = Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->call('refreshProgress')
            ->assertNoRedirect();

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'skipped_count' => 1,
            'completed_at' => now(),
        ])->save();

        $component
            ->call('refreshProgress')
            ->assertRedirectToRoute('options.refetch-tags.show', $run);
    }
}
