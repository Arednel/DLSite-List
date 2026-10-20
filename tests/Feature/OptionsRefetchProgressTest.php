<?php

namespace Tests\Feature;

use App\Livewire\OptionsRefetchProgress;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Support\Refetch\RefetchService;
use DOMDocument;
use DOMXPath;
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

        $running = Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->assertSee('0 / 1 work processed')
            ->assertSeeInOrder(['Fetched', 'Failed', 'Total'])
            ->assertSee('Cancel Refetch');
        $this->assertSame(['refreshProgress'], $this->pollingHandlers($running->html()));

        $run->forceFill([
            'status' => RefetchRun::STATUS_CANCELLING,
            'cancelled_at' => now(),
        ])->save();

        $cancelling = Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->assertSee('Cancelling')
            ->assertDontSee('Cancel Refetch');
        $this->assertSame(['refreshProgress'], $this->pollingHandlers($cancelling->html()));

        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'completed_at' => now(),
        ])->save();

        $review = Livewire::test(OptionsRefetchProgress::class, ['run' => $run])
            ->assertSee('1 / 1 work processed');
        $this->assertSame([], $this->pollingHandlers($review->html()));
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

    /** @return list<string> */
    private function pollingHandlers(string $html): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($document);
        $handlers = [];

        foreach ($xpath->query('//*') as $element) {
            foreach ($element->attributes ?? [] as $attribute) {
                if (str_starts_with($attribute->nodeName, 'wire:poll')) {
                    $handlers[] = trim($attribute->nodeValue);
                }
            }
        }

        return $handlers;
    }
}
