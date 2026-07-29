<?php

namespace Tests\Feature;

use App\Enums\ProductProgress;
use App\Livewire\ProductIndex;
use App\Models\Option;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OptionalProductStatusesTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('optionalStatusCombinationProvider')]
    public function test_forms_show_only_core_and_individually_enabled_statuses(
        bool $onHoldEnabled,
        bool $droppedEnabled,
    ): void {
        Option::setOptionalProductStatuses([
            'on_hold' => $onHoldEnabled,
            'dropped' => $droppedEnabled,
        ]);

        foreach (['/create', '/create/custom'] as $url) {
            $response = $this->get($url)->assertOk();

            foreach (
                [
                    ProductProgress::Listening,
                    ProductProgress::Completed,
                    ProductProgress::PlanToListen,
                ] as $progress
            ) {
                $response->assertSee('value="' . $progress->value . '"', false);
            }

            $this->assertProgressOptionVisibility($response->getContent(), ProductProgress::OnHold, $onHoldEnabled);
            $this->assertProgressOptionVisibility($response->getContent(), ProductProgress::Dropped, $droppedEnabled);
        }
    }

    #[DataProvider('optionalStatusCombinationProvider')]
    public function test_edit_also_shows_the_products_current_optional_status(
        bool $onHoldEnabled,
        bool $droppedEnabled,
    ): void {
        Option::setOptionalProductStatuses([
            'on_hold' => $onHoldEnabled,
            'dropped' => $droppedEnabled,
        ]);

        foreach ([ProductProgress::OnHold, ProductProgress::Dropped] as $currentProgress) {
            $product = Product::factory()->create([
                'progress' => $currentProgress->value,
            ]);
            $response = $this->get("/edit/{$product->id}")->assertOk();

            $this->assertProgressOptionVisibility(
                $response->getContent(),
                ProductProgress::OnHold,
                $onHoldEnabled || $currentProgress === ProductProgress::OnHold,
            );
            $this->assertProgressOptionVisibility(
                $response->getContent(),
                ProductProgress::Dropped,
                $droppedEnabled || $currentProgress === ProductProgress::Dropped,
            );
        }
    }

    #[DataProvider('optionalStatusCombinationProvider')]
    public function test_index_controls_and_advanced_filter_follow_each_switch(
        bool $onHoldEnabled,
        bool $droppedEnabled,
    ): void {
        Option::setOptionalProductStatuses([
            'on_hold' => $onHoldEnabled,
            'dropped' => $droppedEnabled,
        ]);

        $component = Livewire::test(ProductIndex::class);

        $this->assertIndexControlVisibility($component->html(), ProductProgress::OnHold, $onHoldEnabled);
        $this->assertIndexControlVisibility($component->html(), ProductProgress::Dropped, $droppedEnabled);

        if ($onHoldEnabled && $droppedEnabled) {
            $component->assertSeeInOrder([
                'All ASMR',
                'Currently Listening',
                'Completed',
                'On Hold',
                'Dropped',
                'Plan to Listen',
            ]);
        }
    }

    public function test_disabled_optional_status_rows_keep_labels_and_yellow_or_red_bars(): void
    {
        Product::factory()->create([
            'work_name' => 'ON_HOLD_ROW_TOKEN',
            'progress' => ProductProgress::OnHold->value,
        ]);
        Product::factory()->create([
            'work_name' => 'DROPPED_ROW_TOKEN',
            'progress' => ProductProgress::Dropped->value,
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSee('ON_HOLD_ROW_TOKEN')
            ->assertSee('DROPPED_ROW_TOKEN')
            ->assertSee('progress-on-hold', false)
            ->assertSee('progress-dropped', false)
            ->assertSee('On Hold')
            ->assertSee('Dropped');

        $css = file_get_contents(public_path('css/index.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString(
            '.data.status.progress-on-hold',
            $css,
        );
        $this->assertStringContainsString('background-color: #FEE39E;', $css);
        $this->assertStringContainsString(
            '.data.status.progress-dropped',
            $css,
        );
        $this->assertStringContainsString('background-color: #EE9898;', $css);
    }

    public static function optionalStatusCombinationProvider(): iterable
    {
        yield 'both disabled' => [false, false];
        yield 'only On Hold enabled' => [true, false];
        yield 'only Dropped enabled' => [false, true];
        yield 'both enabled' => [true, true];
    }

    private function assertProgressOptionVisibility(
        string $html,
        ProductProgress $progress,
        bool $visible,
    ): void {
        $option = 'value="' . $progress->value . '"';

        $visible
            ? $this->assertStringContainsString($option, $html)
            : $this->assertStringNotContainsString($option, $html);
    }

    private function assertIndexControlVisibility(
        string $html,
        ProductProgress $progress,
        bool $visible,
    ): void {
        $encodedValue = str_replace(' ', '%20', $progress->value);
        $link = 'href="/?progress=' . $encodedValue . '"';
        $option = '<option value="' . $progress->value . '">';

        if ($visible) {
            $this->assertStringContainsString($link, $html);
            $this->assertStringContainsString($option, $html);

            return;
        }

        $this->assertStringNotContainsString($link, $html);
        $this->assertStringNotContainsString($option, $html);
    }
}
