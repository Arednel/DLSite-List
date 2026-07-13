<?php

namespace Tests\Feature;

use App\Livewire\IndexPaginationSettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndexPaginationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_mounts_with_the_default_page_size_when_no_option_exists(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->assertSet('mode', (string) Option::DEFAULT_INDEX_PER_PAGE)
            ->assertSet('customValue', '')
            ->assertSet('saved', false)
            ->assertSet('notice', '');

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, Option::indexPerPage());
    }

    #[DataProvider('fixedPageSizeProvider')]
    public function test_it_saves_a_fixed_page_size(int $pageSize): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', (string) $pageSize)
            ->set('customValue', '777')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('mode', (string) $pageSize)
            ->assertSet('customValue', '')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index pagination setting saved.');

        $this->assertSame($pageSize, Option::indexPerPage());
        $this->assertDatabaseHas('options', [
            'key' => Option::INDEX_PER_PAGE,
            'value' => (string) $pageSize,
        ]);
    }

    public function test_it_saves_a_custom_positive_page_size(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', 'custom')
            ->set('customValue', '333')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('mode', 'custom')
            ->assertSet('customValue', '333')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index pagination setting saved.');

        $this->assertSame(333, Option::indexPerPage());
        $this->assertDatabaseHas('options', [
            'key' => Option::INDEX_PER_PAGE,
            'value' => '333',
        ]);
    }

    public function test_it_saves_unlimited_pagination(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', Option::INDEX_PER_PAGE_UNLIMITED)
            ->set('customValue', '333')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('mode', Option::INDEX_PER_PAGE_UNLIMITED)
            ->assertSet('customValue', '')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index pagination setting saved.');

        $this->assertSame(Option::INDEX_PER_PAGE_UNLIMITED, Option::indexPerPage());
        $this->assertDatabaseHas('options', [
            'key' => Option::INDEX_PER_PAGE,
            'value' => Option::INDEX_PER_PAGE_UNLIMITED,
        ]);
    }

    public function test_changing_component_state_does_not_persist_until_save_is_called(): void
    {
        Option::setIndexPerPage(25);

        Livewire::test(IndexPaginationSettings::class)
            ->assertSet('mode', '25')
            ->set('mode', '50')
            ->assertSet('mode', '50');

        $this->assertSame(25, Option::indexPerPage());
        $this->assertDatabaseHas('options', [
            'key' => Option::INDEX_PER_PAGE,
            'value' => '25',
        ]);
    }

    public function test_it_hydrates_custom_mode_from_a_non_fixed_saved_value(): void
    {
        Option::setIndexPerPage(333);

        Livewire::test(IndexPaginationSettings::class)
            ->assertSet('mode', 'custom')
            ->assertSet('customValue', '333');
    }

    public function test_it_hydrates_unlimited_mode_from_the_saved_value(): void
    {
        Option::setIndexPerPage(Option::INDEX_PER_PAGE_UNLIMITED);

        Livewire::test(IndexPaginationSettings::class)
            ->assertSet('mode', Option::INDEX_PER_PAGE_UNLIMITED)
            ->assertSet('customValue', '');
    }

    #[DataProvider('invalidModeProvider')]
    public function test_it_rejects_invalid_modes(string $mode): void
    {
        Option::setIndexPerPage(25);

        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', $mode)
            ->call('save')
            ->assertHasErrors('mode')
            ->assertSet('saved', false)
            ->assertSet('notice', '');

        $this->assertSame(25, Option::indexPerPage());
    }

    #[DataProvider('invalidCustomValueProvider')]
    public function test_it_rejects_invalid_custom_values(string $customValue): void
    {
        Option::setIndexPerPage(25);

        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', 'custom')
            ->set('customValue', $customValue)
            ->call('save')
            ->assertHasErrors('customValue')
            ->assertSet('saved', false)
            ->assertSet('notice', '');

        $this->assertSame(25, Option::indexPerPage());
    }

    public function test_it_clears_saved_notice_when_the_user_changes_a_tracked_field(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', '25')
            ->call('save')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index pagination setting saved.')
            ->set('mode', '50')
            ->assertSet('saved', false)
            ->assertSet('notice', '');
    }

    public function test_it_resets_to_the_default_page_size_after_confirmation(): void
    {
        Option::setIndexPerPage(250);

        Livewire::test(IndexPaginationSettings::class)
            ->assertSet('mode', '250')
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertHasNoErrors()
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('mode', (string) Option::DEFAULT_INDEX_PER_PAGE)
            ->assertSet('customValue', '')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index pagination reset to default.');

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, Option::indexPerPage());
        $this->assertDatabaseMissing('options', [
            'key' => Option::INDEX_PER_PAGE,
        ]);
    }

    public function test_it_can_cancel_reset_confirmation_without_changing_the_saved_value(): void
    {
        Option::setIndexPerPage(250);

        Livewire::test(IndexPaginationSettings::class)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('cancelResetToDefault')
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('mode', '250');

        $this->assertSame(250, Option::indexPerPage());
    }

    public function test_it_clears_saved_notice_when_custom_value_changes_after_save(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->set('mode', 'custom')
            ->set('customValue', '333')
            ->call('save')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index pagination setting saved.')
            ->set('customValue', '444')
            ->assertSet('saved', false)
            ->assertSet('notice', '');
    }

    public function test_it_refreshes_from_settings_and_clears_notice_after_global_defaults_reset(): void
    {
        Option::setIndexPerPage(25);

        $component = Livewire::test(IndexPaginationSettings::class)
            ->set('mode', '50')
            ->call('save')
            ->assertSet('mode', '50')
            ->assertSet('saved', true);

        Option::setIndexPerPage(500);

        $component
            ->call('refreshFromSettings')
            ->assertSet('mode', '500')
            ->assertSet('customValue', '')
            ->assertSet('saved', false)
            ->assertSet('notice', '');
    }

    public function test_it_passes_supported_option_values_to_the_view(): void
    {
        Livewire::test(IndexPaginationSettings::class)
            ->assertViewHas('fixedOptions', Option::fixedIndexPerPageOptions())
            ->assertViewHas('unlimitedValue', Option::INDEX_PER_PAGE_UNLIMITED);
    }

    public static function fixedPageSizeProvider(): iterable
    {
        foreach (Option::FIXED_INDEX_PER_PAGE_OPTIONS as $pageSize) {
            yield "{$pageSize} works per page" => [$pageSize];
        }
    }

    public static function invalidModeProvider(): iterable
    {
        yield 'empty mode' => [''];
        yield 'unknown string' => ['not-a-page-size'];
        yield 'numeric but not configured fixed option' => ['333'];
        yield 'zero' => ['0'];
        yield 'negative number' => ['-10'];
    }

    public static function invalidCustomValueProvider(): iterable
    {
        yield 'empty custom value' => [''];
        yield 'zero custom value' => ['0'];
        yield 'negative custom value' => ['-10'];
        yield 'non-integer custom value' => ['abc'];
        yield 'decimal custom value' => ['10.5'];
    }
}
