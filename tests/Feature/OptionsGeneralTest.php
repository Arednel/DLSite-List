<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\Product;
use App\Support\Refetch\RefetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptionsGeneralTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_page_renders_general_tab_by_default(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'OPTIONS_WORK_TOKEN',
            'work_name_english' => 'OPTIONS_EN_TOKEN',
        ]);

        $this->get('/options')
            ->assertOk()
            ->assertSee('Options')
            ->assertSee('href="/options?tab=general"', false)
            ->assertSee('href="/options?tab=field-layouts"', false)
            ->assertSee('href="/options?tab=authentication"', false)
            ->assertSee('href="/options?tab=refetch"', false)
            ->assertSee('Index Pagination')
            ->assertSee('Overflow')
            ->assertSee('Add/Edit Modals')
            ->assertSee('Reset All Options')
            ->assertDontSee('Index Sort Menu')
            ->assertDontSee('Refetch all works')
            ->assertDontSee('OPTIONS_WORK_TOKEN')
            ->assertDontSee('OPTIONS_EN_TOKEN');
    }

    public function test_shared_quick_add_modal_configuration_renders_on_content_pages(): void
    {
        Option::setProductFormModalEnabled(true);
        Option::setProductFormModalCompletionAction(Option::PRODUCT_FORM_MODAL_COMPLETION_CLOSE);
        $product = Product::factory()->create();
        $run = app(RefetchService::class)->createRun([$product->id], false);

        foreach (['/options', '/tags', route('options.refetch.show', $run, false)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('href="/create"', false)
                ->assertDontSee('href="/create?modal=1"', false)
                ->assertSee('data-work-form-modal-link', false)
                ->assertSee('data-enabled="true"', false)
                ->assertSee('data-completion-action="close"', false);
        }
    }

    public function test_field_layouts_tab_renders_field_layout_settings(): void
    {
        $this->get('/options?tab=field-layouts')
            ->assertOk()
            ->assertSee('Field Layouts')
            ->assertSee('Index Table Columns')
            ->assertSee('Index Sort Menu')
            ->assertSee('Fetched EN Tags')
            ->assertDontSee('Index page size')
            ->assertDontSee('Refetch all works');
    }

    public function test_active_option_category_is_exposed_as_the_current_navigation_page(): void
    {
        foreach (['general', 'field-layouts', 'authentication', 'refetch'] as $tab) {
            $response = $this->get('/options?tab=' . $tab)
                ->assertOk()
                ->assertSee('data-active-options-category="' . $tab . '"', false)
                ->assertSee('href="/options?tab=' . $tab . '"', false)
                ->assertSee('aria-current="page"', false);

            $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
        }
    }

    public function test_invalid_options_tab_falls_back_to_general(): void
    {
        $this->get('/options?tab=options')
            ->assertOk()
            ->assertSee('Index Pagination')
            ->assertDontSee('Index Sort Menu')
            ->assertDontSee('Refetch all works');
    }

    public function test_refetch_tab_shows_empty_state_when_there_are_no_works(): void
    {
        $this->get('/options?tab=refetch')
            ->assertOk()
            ->assertSee('Refetch DLSite Data')
            ->assertSee('No works available for refetch.')
            ->assertSee('Clean up refetch data')
            ->assertDontSee('Go to latest refetch')
            ->assertDontSee('Refetch selected works');
    }

    public function test_refetch_tab_links_only_to_the_latest_run(): void
    {
        $product = Product::factory()->create(['work_name' => 'LATEST_REFETCH_LINK_TOKEN']);
        $olderRun = app(RefetchService::class)->createRun([$product->id], false);
        $latestRun = app(RefetchService::class)->createRun([$product->id], false);

        $this->get('/options?tab=refetch')
            ->assertOk()
            ->assertSee('Go to latest refetch')
            ->assertSeeInOrder([
                'Go to latest refetch',
                'Clean up refetch data',
            ])
            ->assertSee('About refetch cleanup')
            ->assertSee('class="refetch-scope-grid"', false)
            ->assertSeeInOrder([
                'Refetch All Works',
                'Fetch every work in the library in one refetch run.',
                'Refetch Selected Works',
                'Search and choose works to include in this refetch run.',
            ])
            ->assertSee('Refetch Images')
            ->assertSee('Downloads the current cover and sample images for every selected work')
            ->assertSee('href="' . route('options.refetch.show', $latestRun) . '"', false)
            ->assertDontSee('href="' . route('options.refetch.show', $olderRun) . '"', false);
    }
}
