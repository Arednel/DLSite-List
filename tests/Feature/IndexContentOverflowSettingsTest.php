<?php

namespace Tests\Feature;

use App\Livewire\IndexContentOverflowSettings;
use App\Models\Option;
use App\Support\ProductIndexContentOverflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexContentOverflowSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_safe_defaults_and_renders_all_three_controls(): void
    {
        Livewire::test(IndexContentOverflowSettings::class)
            ->assertSet('overflow', ProductIndexContentOverflow::DEFAULTS)
            ->assertSee('Limit Inline Notes height')
            ->assertSee('Limit Notes Column height')
            ->assertSee('Limit Tags Column height');
    }

    public function test_it_saves_all_targets_and_normalizes_heights(): void
    {
        Livewire::test(IndexContentOverflowSettings::class)
            ->set('overflow.inline_notes.enabled', true)
            ->set('overflow.inline_notes.height', '120px')
            ->set('overflow.notes_column.enabled', true)
            ->set('overflow.notes_column.height', ' 4.5REM ')
            ->set('overflow.tags.enabled', true)
            ->set('overflow.tags.height', '40%')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('overflow.inline_notes.height', '120px')
            ->assertSet('overflow.notes_column.height', '4.5rem')
            ->assertSet('overflow.tags.height', '40%')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Overflow settings saved.');

        $this->assertSame([
            'inline_notes' => ['enabled' => true, 'height' => '120px'],
            'notes_column' => ['enabled' => true, 'height' => '4.5rem'],
            'tags' => ['enabled' => true, 'height' => '40%'],
        ], Option::indexContentOverflow());
    }

    public function test_it_rejects_an_invalid_height_without_persisting(): void
    {
        Livewire::test(IndexContentOverflowSettings::class)
            ->set('overflow.inline_notes.enabled', true)
            ->set('overflow.inline_notes.height', 'calc(100%)')
            ->call('save')
            ->assertHasErrors(['overflow.inline_notes.height']);

        $this->assertDatabaseMissing('options', ['key' => Option::INDEX_CONTENT_OVERFLOW]);
    }

    public function test_disabling_targets_restores_default_heights_and_ignores_hidden_drafts(): void
    {
        Option::setIndexContentOverflow([
            'inline_notes' => ['enabled' => true, 'height' => '6rem'],
            'notes_column' => ['enabled' => true, 'height' => '120px'],
        ]);

        Livewire::test(IndexContentOverflowSettings::class)
            ->set('overflow.inline_notes.height', 'invalid')
            ->call('save')
            ->assertHasErrors(['overflow.inline_notes.height'])
            ->set('overflow.inline_notes.enabled', false)
            ->assertHasNoErrors()
            ->set('overflow.notes_column.height', '2rem')
            ->set('overflow.notes_column.enabled', false)
            ->set('overflow.tags.height', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('overflow.inline_notes.height', '80px')
            ->assertSet('overflow.notes_column.height', '80px')
            ->assertSet('overflow.tags.height', '80px')
            ->assertSet('saved', true)
            ->set('overflow.tags.enabled', true)
            ->assertSet('overflow.tags.height', '80px')
            ->assertSet('saved', false)
            ->assertSet('notice', '');

        $this->assertSame([
            'inline_notes' => ['enabled' => false, 'height' => '80px'],
            'notes_column' => ['enabled' => false, 'height' => '80px'],
            'tags' => ['enabled' => false, 'height' => '80px'],
        ], Option::indexContentOverflow());
    }

    public function test_it_hydrates_and_resets_after_confirmation(): void
    {
        Option::setIndexContentOverflow([
            'inline_notes' => ['enabled' => true, 'height' => '5rem'],
            'notes_column' => ['enabled' => true, 'height' => '30vh'],
            'tags' => ['enabled' => true, 'height' => '40%'],
        ]);

        Livewire::test(IndexContentOverflowSettings::class)
            ->assertSet('overflow', [
                'inline_notes' => ['enabled' => true, 'height' => '5rem'],
                'notes_column' => ['enabled' => true, 'height' => '30vh'],
                'tags' => ['enabled' => true, 'height' => '40%'],
            ])
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('overflow', ProductIndexContentOverflow::DEFAULTS)
            ->assertSet('notice', 'Overflow settings reset to default.');

        $this->assertDatabaseMissing('options', ['key' => Option::INDEX_CONTENT_OVERFLOW]);
    }

    public function test_it_refreshes_after_global_reset(): void
    {
        Option::setIndexContentOverflow([
            'inline_notes' => ['enabled' => true, 'height' => '2rem'],
        ]);

        $component = Livewire::test(IndexContentOverflowSettings::class)
            ->assertSet('overflow.inline_notes.enabled', true);

        Option::resetVisibleSettingsToDefault();

        $component
            ->dispatch('options-defaults-reset')
            ->assertSet('overflow', ProductIndexContentOverflow::DEFAULTS)
            ->assertSet('saved', false)
            ->assertSet('notice', '');
    }
}
