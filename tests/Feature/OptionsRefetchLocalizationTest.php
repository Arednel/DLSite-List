<?php

namespace Tests\Feature;

use App\Enums\RefetchCategory;
use App\Enums\UiLanguage;
use App\Livewire\OptionsResetDefaults;
use App\Models\Option;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\Refetch\RefetchService;
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
            ->assertSee('作品を再取得')
            ->assertSee('RJ IDまたはタイトルで検索…')
            ->assertSee('value="selected"', false);
    }

    public function test_japanese_refetch_review_localizes_controls_but_keeps_content_and_action_values_raw(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);
        $fetchedProduct = Product::factory()->create(['work_name' => 'RAW_WORK_TITLE_TOKEN']);
        $skippedProduct = Product::factory()->create(['work_name' => 'RAW_SKIPPED_TITLE_TOKEN']);
        $run = app(RefetchService::class)->createRun([$fetchedProduct->id, $skippedProduct->id], false);

        $run->results()->where('product_id', $fetchedProduct->id)->firstOrFail()->forceFill([
            'status' => RefetchWorkResult::STATUS_FETCHED,
            'changes' => [
                RefetchCategory::Titles->value => [
                    'work_name' => [
                        'label' => 'Japanese Title',
                        'old' => 'RAW_WORK_TITLE_TOKEN',
                        'new' => 'RAW_NEW_TITLE_TOKEN',
                    ],
                ],
                RefetchCategory::Tags->value => [
                    'tags' => [
                        'label' => 'Fetched Tags',
                        'old' => ['RAW_STALE_JP_TOKEN'],
                        'new' => ['RAW_JP_TAG_TOKEN', 'RAW_EN_TAG_TOKEN'],
                        'details' => [
                            'added_japanese' => ['RAW_JP_TAG_TOKEN'],
                            'added_english' => ['RAW_EN_TAG_TOKEN'],
                            'stale_japanese' => ['RAW_STALE_JP_TOKEN'],
                            'stale_english' => [],
                            'custom_to_fetched' => ['RAW_CUSTOM_TAG_TOKEN'],
                        ],
                    ],
                ],
            ],
        ])->save();
        $run->results()->where('product_id', $skippedProduct->id)->firstOrFail()->forceFill([
            'status' => RefetchWorkResult::STATUS_FAILED,
            'error' => 'UPSTREAM_RAW_ERROR_TOKEN',
        ])->save();
        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 2,
            'fetched_count' => 1,
            'failed_count' => 1,
            'completed_at' => now(),
            'resolved_tabs' => array_values(array_diff(
                RefetchCategory::values(),
                [RefetchCategory::Titles->value, RefetchCategory::Tags->value],
            )),
        ])->save();

        $this->get(route('options.refetch.show', $run))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('<title>作品を再取得</title>', false)
            ->assertSee('確認')
            ->assertSee('タイトル')
            ->assertSee('新規JP')
            ->assertSee('取得済みタグとして追加')
            ->assertSee('カスタムタグに変更')
            ->assertSee('すべてを「上書き」に設定')
            ->assertSee('変更がある未処理の各タブの全体選択を「上書き」に設定します。')
            ->assertSee('すべてのタブを適用')
            ->assertSee('RAW_WORK_TITLE_TOKEN')
            ->assertSee('RAW_NEW_TITLE_TOKEN')
            ->assertSee('RAW_JP_TAG_TOKEN')
            ->assertSee('UPSTREAM_RAW_ERROR_TOKEN')
            ->assertSee('value="move_to_custom"', false)
            ->assertSee('wire:model="globalActions.tags"', false);
    }

    public function test_japanese_refetch_validation_messages_are_localized_without_changing_run_state(): void
    {
        Bus::fake();
        Option::setUiLanguage(UiLanguage::Japanese);

        $this->from(route('options.index', ['tab' => 'refetch']))
            ->post(route('options.refetch.start'), [
                'scope' => 'selected',
                'product_ids' => [],
                'tab' => 'refetch',
            ])
            ->assertRedirect(route('options.index', ['tab' => 'refetch']))
            ->assertSessionHasErrors([
                'product_ids' => '再取得する作品を1件以上選択してください。',
            ]);

        $product = Product::factory()->create();
        $run = app(RefetchService::class)->createRun([$product->id], false);
        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'completed_at' => now(),
        ])->save();

        $this->post(route('options.refetch.cancel', $run))
            ->assertRedirect(route('options.refetch.show', $run))
            ->assertSessionHasErrors([
                'run' => '実行中の再取得のみキャンセルできます。',
            ]);

        $this->assertSame(RefetchRun::STATUS_REVIEW, $run->refresh()->status);
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
