<?php

namespace Tests\Feature;

use App\Livewire\IndexPaginationSettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class IndexPaginationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_component_defaults_to_100_without_saved_option(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->assertSet('mode', '100')
            ->assertSet('customValue', '');
    }

    public function test_settings_component_saves_fixed_custom_and_unlimited_values(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', '250')
            ->call('save')
            ->assertSee('Index pagination setting saved.');

        $this->assertSame(250, Option::indexPerPage());
        $this->assertSame('250', DB::table('options')->where('key', Option::INDEX_PER_PAGE)->value('value'));

        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', 'custom')
            ->set('customValue', '12345')
            ->call('save')
            ->assertSet('mode', 'custom')
            ->assertSet('customValue', '12345');

        $this->assertSame(12345, Option::indexPerPage());
        $this->assertSame('12345', DB::table('options')->where('key', Option::INDEX_PER_PAGE)->value('value'));

        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', Option::INDEX_PER_PAGE_UNLIMITED)
            ->call('save');

        $this->assertSame(Option::INDEX_PER_PAGE_UNLIMITED, Option::indexPerPage());
        $this->assertSame(
            Option::INDEX_PER_PAGE_UNLIMITED,
            DB::table('options')->where('key', Option::INDEX_PER_PAGE)->value('value')
        );
    }

    public function test_settings_component_saves_only_when_submitted(): void
    {
        $component = Livewire::test(IndexPaginationSettings::class)
            ->set('mode', '250');

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, Option::indexPerPage());

        $component->call('save');

        $this->assertSame(250, Option::indexPerPage());
    }

    public function test_settings_component_clears_saved_notice_when_inputs_change(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', '250')
            ->call('save')
            ->assertSet('saved', true)
            ->assertSee('Index pagination setting saved.')
            ->set('mode', '500')
            ->assertSet('saved', false)
            ->assertDontSee('Index pagination setting saved.');

        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', 'custom')
            ->set('customValue', '25')
            ->call('save')
            ->assertSet('saved', true)
            ->assertSee('Index pagination setting saved.')
            ->set('customValue', '30')
            ->assertSet('saved', false)
            ->assertDontSee('Index pagination setting saved.');
    }

    public function test_settings_component_rejects_non_positive_custom_values(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', 'custom')
            ->set('customValue', '0')
            ->call('save')
            ->assertHasErrors(['customValue']);

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, Option::indexPerPage());
    }

    public function test_settings_component_rejects_non_integer_custom_values(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', 'custom')
            ->set('customValue', '1.5')
            ->call('save')
            ->assertHasErrors(['customValue']);

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, Option::indexPerPage());
    }

    public function test_settings_component_uses_livewire_mode_state_without_persisting_until_save(): void
    {
        $component = Livewire::test(IndexPaginationSettings::class)
            ->assertSee('wire:model.change.live="mode"', false)
            ->assertDontSee('Custom works per page')
            ->set('mode', 'custom')
            ->assertSee('Custom works per page');

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, Option::indexPerPage());

        $component
            ->set('customValue', '25')
            ->call('save');

        $this->assertSame(25, Option::indexPerPage());
    }

    public function test_settings_component_keeps_the_same_fixed_choices_and_labels(): void
    {
        $component = Livewire::test(IndexPaginationSettings::class);

        foreach ([10, 25, 50, 100, 250, 500, 1000] as $value) {
            $component->assertSee("{$value} works per page");
        }

        $component
            ->assertSee('Custom value')
            ->assertSee('Unlimited');
    }
}
