<?php

namespace Tests\Feature;

use App\Enums\BulkImportItemStatus;
use App\Enums\BulkImportRunStatus;
use App\Enums\FetchedTagLanguage;
use App\Enums\LibraryTransferDirection;
use App\Enums\LibraryTransferRunStatus;
use App\Enums\ProductProgress;
use App\Enums\UiLanguage;
use App\Livewire\UiLanguageSettings;
use App\Models\BulkImportRun;
use App\Models\LibraryTransferRun;
use App\Models\Option;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Models\User;
use App\Support\Refetch\RefetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TraditionalChineseUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_traditional_chinese_localizes_subsequent_requests_and_preserves_the_manual_tag_language(): void
    {
        Livewire::test(UiLanguageSettings::class)
            ->set('language', UiLanguage::TraditionalChinese->value)
            ->set('followFetchedTagsLanguage', true)
            ->set('fetchedTagLanguage', FetchedTagLanguage::Japanese->value)
            ->assertSee('value="en" selected', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('options.index', ['tab' => 'general']);

        $this->assertSame(UiLanguage::TraditionalChinese, Option::uiLanguage());
        $this->assertSame(FetchedTagLanguage::Japanese, Option::fetchedTagLanguage());

        $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('<html lang="zh-TW">', false)
            ->assertSee('介面語言')
            ->assertSee('繁體中文')
            ->assertDontSee('Language settings saved.');

        $this->assertSame(FetchedTagLanguage::English, Option::effectiveFetchedTagLanguage());

        Livewire::test(UiLanguageSettings::class)
            ->set('followFetchedTagsLanguage', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(FetchedTagLanguage::Japanese, Option::effectiveFetchedTagLanguage());
    }

    public function test_library_and_form_pages_keep_user_content_and_stored_values_while_localizing_the_shell(): void
    {
        Option::setUiLanguage(UiLanguage::TraditionalChinese);
        $product = Product::factory()->create([
            'work_name' => 'USER_WORK_TITLE_TOKEN',
            'progress' => ProductProgress::Listening->value,
        ]);

        foreach ([
            route('index'),
            route('products.create'),
            route('products.create.custom'),
            route('products.create.bulk'),
            route('products.edit', $product),
            route('tags.index'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<html lang="zh-TW"', false);
        }

        $this->get(route('index'))
            ->assertSee('USER_WORK_TITLE_TOKEN')
            ->assertSee('placeholder="搜尋..."', false);

        $this->assertSame('USER_WORK_TITLE_TOKEN', $product->fresh()->work_name);
        $this->assertSame(ProductProgress::Listening->value, $product->fresh()->progress);

        $this->assertStringContainsString(
            '<html lang="zh-TW">',
            view('WorkFormCompleted', ['redirectUrl' => '/'])->render(),
        );
    }

    public function test_authentication_and_framework_validation_use_traditional_chinese(): void
    {
        Option::setUiLanguage(UiLanguage::TraditionalChinese);
        Option::setUserAuthenticationEnabled(true);

        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('<html lang="zh-TW">', false)
            ->assertDontSee('Create Administrator Account');

        $this->from(route('admin.setup'))
            ->post(route('admin.setup.store'), [])
            ->assertSessionHasErrors(['username', 'password']);

        $errors = session('errors')->getBag('default');
        $this->assertSame(__('validation.required', ['attribute' => __('validation.attributes.username')]), $errors->first('username'));
        $this->assertStringNotContainsString('required', $errors->first('password'));

        User::factory()->create();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="zh-TW">', false)
            ->assertDontSee('Sign in to continue');

        $this->post(route('login.authenticate'), ['username' => 'invalid', 'password' => 'invalid'])
            ->assertSessionHasErrors([
                'username' => __('errors.The provided credentials are incorrect.'),
            ]);
    }

    public function test_options_import_history_and_review_pages_use_chinese_but_keep_canonical_data(): void
    {
        Option::setUiLanguage(UiLanguage::TraditionalChinese);
        $bulk = BulkImportRun::create([
            'status' => BulkImportRunStatus::Completed,
            'input_snapshot' => [], 'total_count' => 1, 'processed_count' => 1,
            'failed_count' => 1, 'completed_at' => now(),
        ]);
        $item = $bulk->items()->create([
            'position' => 1, 'product_id' => 'RJ99999004',
            'status' => BulkImportItemStatus::Failed,
            'error' => 'GeoBlocked DLSite work', 'completed_at' => now(),
        ]);

        foreach (['general', 'field-layouts', 'authentication', 'refetch', 'transfers', 'bulk-imports'] as $tab) {
            $this->get(route('options.index', ['tab' => $tab]))
                ->assertOk()
                ->assertSee('<html lang="zh-TW">', false)
                ->assertSee('data-active-options-category="' . $tab . '"', false)
                ->assertDontSee('<title>Options</title>', false);
        }

        $this->get(route('options.index', ['tab' => 'bulk-imports']))
            ->assertSee('RJ99999004')
            ->assertDontSee('GeoBlocked DLSite work');
        $this->assertSame('GeoBlocked DLSite work', $item->fresh()->error);
        $this->assertSame(BulkImportItemStatus::Failed, $item->fresh()->status);

        $product = Product::factory()->create(['work_name' => 'RAW_TITLE_TOKEN']);
        $failedProduct = Product::factory()->create();
        $refetch = app(RefetchService::class)->createRun([$product->id, $failedProduct->id], false);
        $refetch->results()->where('product_id', $product->id)->firstOrFail()->forceFill([
            'status' => RefetchWorkResult::STATUS_FETCHED,
            'changes' => ['titles' => ['work_name' => [
                'label' => 'Japanese Title', 'old' => 'RAW_TITLE_TOKEN', 'new' => 'RAW_NEW_TITLE_TOKEN',
            ]]],
        ])->save();
        $refetch->results()->where('product_id', $failedProduct->id)->firstOrFail()->forceFill([
            'status' => RefetchWorkResult::STATUS_FAILED, 'error' => 'RAW_DIAGNOSTIC_TOKEN',
        ])->save();
        $refetch->forceFill([
            'status' => RefetchRun::STATUS_REVIEW, 'processed_count' => 2,
            'failed_count' => 1, 'fetched_count' => 1, 'completed_at' => now(),
        ])->save();
        $this->get(route('options.refetch.show', $refetch))
            ->assertOk()
            ->assertSee('<html lang="zh-TW">', false)
            ->assertSee('RAW_TITLE_TOKEN')
            ->assertSee('RAW_DIAGNOSTIC_TOKEN');

        $transfer = LibraryTransferRun::create([
            'direction' => LibraryTransferDirection::Import,
            'status' => LibraryTransferRunStatus::Review, 'settings' => [],
        ]);
        $this->get(route('options.transfers.show', $transfer))
            ->assertOk()
            ->assertSee('<html lang="zh-TW">', false)
            ->assertDontSee('Review is unavailable while background processing is running.');
    }
}
