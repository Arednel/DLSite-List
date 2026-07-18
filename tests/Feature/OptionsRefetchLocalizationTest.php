<?php

namespace Tests\Feature;

use App\Enums\UiLanguage;
use App\Livewire\OptionsResetDefaults;
use App\Models\Option;
use App\Models\Product;
use App\Models\TagRefetchRun;
use App\Models\TagRefetchWorkResult;
use App\Support\TagRefetch\TagRefetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class OptionsRefetchLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_japanese_options_tabs_localize_the_app_owned_surface_and_preserve_stable_values(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);

        $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('<title>設定</title>', false)
            ->assertSee('aria-label="設定メニュー"', false)
            ->assertSee('表示言語')
            ->assertSee('すべての設定をリセット')
            ->assertSee('href="/options?tab=refetch"', false);

        $this->get(route('options.index', ['tab' => 'field-layouts']))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('フィールドレイアウト')
            ->assertSee('一覧表の項目')
            ->assertSee('aria-label="更新日をドラッグ"', false)
            ->assertSee('編集可能');

        $this->get(route('options.index', ['tab' => 'refetch']))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('タグを再取得')
            ->assertSee('RJ IDまたはタイトルで検索…')
            ->assertSee('value="selected"', false);
    }

    public function test_japanese_refetch_review_localizes_controls_but_keeps_content_and_action_values_raw(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);
        $fetchedProduct = Product::factory()->create(['work_name' => 'RAW_WORK_TITLE_TOKEN']);
        $skippedProduct = Product::factory()->create(['work_name' => 'RAW_SKIPPED_TITLE_TOKEN']);
        $run = app(TagRefetchService::class)->createRun([$fetchedProduct->id, $skippedProduct->id]);

        $run->results()->where('product_id', $fetchedProduct->id)->firstOrFail()->forceFill([
            'status' => TagRefetchWorkResult::STATUS_FETCHED,
            'fetched_japanese_tags' => ['RAW_JP_TAG_TOKEN'],
            'fetched_english_tags' => ['RAW_EN_TAG_TOKEN'],
            'added_japanese_tags' => ['RAW_JP_TAG_TOKEN'],
            'added_english_tags' => ['RAW_EN_TAG_TOKEN'],
            'stale_japanese_tags' => ['RAW_STALE_JP_TOKEN'],
            'stale_english_tags' => ['RAW_STALE_EN_TOKEN'],
            'custom_to_fetched_japanese_tags' => ['RAW_CUSTOM_TAG_TOKEN'],
            'custom_to_fetched_english_tags' => ['RAW_CUSTOM_EN_TAG_TOKEN'],
        ])->save();
        $run->results()->where('product_id', $skippedProduct->id)->firstOrFail()->forceFill([
            'status' => TagRefetchWorkResult::STATUS_SKIPPED,
            'error' => 'UPSTREAM_RAW_ERROR_TOKEN',
        ])->save();
        $run->forceFill([
            'status' => TagRefetchRun::STATUS_REVIEW,
            'processed_count' => 2,
            'fetched_count' => 1,
            'skipped_count' => 1,
            'completed_at' => now(),
        ])->save();

        $this->get(route('options.refetch-tags.show', $run))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('<title>タグを再取得</title>', false)
            ->assertSee('確認')
            ->assertSee('新規JP')
            ->assertSee('カスタム → 取得済み')
            ->assertSee('title="新規JPタグ"', false)
            ->assertSee('取得済みタグとして追加')
            ->assertSee('カスタムタグに変更')
            ->assertSee('変更を適用')
            ->assertSee('RAW_WORK_TITLE_TOKEN')
            ->assertSee('RAW_JP_TAG_TOKEN')
            ->assertSee('UPSTREAM_RAW_ERROR_TOKEN')
            ->assertSee('value="move_to_custom"', false)
            ->assertSee('name="global_japanese_action"', false);
    }

    public function test_japanese_refetch_validation_messages_are_localized_without_changing_run_state(): void
    {
        Bus::fake();
        Option::setUiLanguage(UiLanguage::Japanese);

        $this->from(route('options.index', ['tab' => 'refetch']))
            ->post(route('options.refetch-tags.start'), [
                'scope' => 'selected',
                'product_ids' => [],
                'tab' => 'refetch',
            ])
            ->assertRedirect(route('options.index', ['tab' => 'refetch']))
            ->assertSessionHasErrors([
                'product_ids' => '再取得する作品を1件以上選択してください。',
            ]);

        $product = Product::factory()->create();
        $run = app(TagRefetchService::class)->createRun([$product->id]);
        $run->forceFill([
            'status' => TagRefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'completed_at' => now(),
        ])->save();

        $this->post(route('options.refetch-tags.cancel', $run))
            ->assertRedirect(route('options.refetch-tags.show', $run))
            ->assertSessionHasErrors([
                'run' => '実行中の再取得のみキャンセルできます。',
            ]);

        $this->assertSame(TagRefetchRun::STATUS_REVIEW, $run->refresh()->status);
    }

    public function test_reset_all_returns_to_the_originating_tab_and_uses_the_destination_english_locale(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);
        Option::setIndexPerPage(25);

        Livewire::test(OptionsResetDefaults::class, ['activeTab' => 'field-layouts'])
            ->call('resetAll')
            ->assertRedirectToRoute('options.index', ['tab' => 'field-layouts']);

        $this->assertSame('All Options settings reset to defaults.', session('options_reset_notice'));
        $this->assertSame(UiLanguage::English, Option::uiLanguage());

        $this->get(route('options.index', ['tab' => 'field-layouts']))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('All Options settings reset to defaults.')
            ->assertSee('href="/options?tab=field-layouts"', false);
    }
}
