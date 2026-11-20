<?php

namespace Tests\Feature;

use App\Enums\DlsiteLanguage;
use App\Enums\RefetchCategory;
use App\Enums\UiLanguage;
use App\Livewire\OptionsRefetchReview;
use App\Livewire\OptionsResetDefaults;
use App\Models\Genre;
use App\Models\Option;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\DLSite\DLSiteFetchResult;
use App\Support\DLSite\DLSiteWorkData;
use App\Support\ProductGenreSync;
use App\Support\Refetch\RefetchDiffBuilder;
use App\Support\Refetch\RefetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OptionsRefetchLocalizationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('savedMetadataLabelFormats')]
    public function test_saved_refetch_metadata_labels_follow_ui_language_without_changing_stored_keys(bool $legacyLabels): void
    {
        Bus::fake();
        Option::setUiLanguage(UiLanguage::Japanese);
        App::setLocale(UiLanguage::Japanese->value);
        $product = Product::factory()
            ->withTitles('Old Japanese title', 'Old English title')
            ->withEnglishDescription('Old English description')
            ->create(['description' => 'Old Japanese description']);
        $changes = app(RefetchDiffBuilder::class)->build(
            $product,
            new DLSiteFetchResult(
                workData: DLSiteWorkData::fromArray(['locales' => [
                    'ja' => [
                        'product_id' => $product->id,
                        'work_name' => 'New Japanese title',
                        'description' => 'New Japanese description',
                    ],
                    'en' => [
                        'work_name' => 'New English title',
                        'description' => 'New English description',
                    ],
                ]]),
                failedImages: [],
            ),
            null,
        );
        $this->assertSame(['日本語タイトル', '英語タイトル'], array_column($changes['titles'], 'label'));
        $this->assertSame(['日本語の説明', '英語の説明'], array_column($changes['descriptions'], 'label'));

        if ($legacyLabels) {
            $changes['titles']['work_name']['label'] = 'Japanese Title';
            $changes['titles']['localization:en:title']['label'] = 'English Title';
            $changes['descriptions']['description']['label'] = 'Japanese Description';
            $changes['descriptions']['localization:en:description']['label'] = 'English Description';
        }

        $run = app(RefetchService::class)->createRun([$product->id], false);
        $result = $run->results()->firstOrFail();
        $result->forceFill(['status' => RefetchWorkResult::STATUS_FETCHED, 'changes' => $changes])->save();
        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'completed_at' => now(),
        ])->save();
        $storedChanges = $result->fresh()->changes;

        foreach (
            [
                'en' => ['Japanese Title', 'English', 'Japanese Description', 'English'],
                'ja' => ['日本語タイトル', '英語', '日本語の説明', '英語'],
                'zh_TW' => ['日文標題', '英文', '日文說明', '英文'],
            ] as $locale => [$japaneseTitle, $englishTitle, $japaneseDescription, $englishDescription]
        ) {
            Option::setUiLanguage($locale);
            App::setLocale($locale);

            Livewire::test(OptionsRefetchReview::class, ['run' => $run])
                ->call('showCategory', RefetchCategory::Titles->value)
                ->assertSee($japaneseTitle)
                ->assertSee($englishTitle)
                ->assertSee('New Japanese title')
                ->assertSee('New English title')
                ->call('showCategory', RefetchCategory::Descriptions->value)
                ->assertSee($japaneseDescription)
                ->assertSee($englishDescription)
                ->assertSee('New Japanese description')
                ->assertSee('New English description');
        }

        $this->assertSame($storedChanges, $result->fresh()->changes);
    }

    public static function savedMetadataLabelFormats(): array
    {
        return [
            'current localized labels' => [false],
            'previous English-string keys' => [true],
        ];
    }

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

        $this->get(route('options.index', ['tab' => 'display']))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('フィールドレイアウト')
            ->assertSee('一覧表の列')
            ->assertSee('チェリー')
            ->assertSee('ブラック')
            ->assertSee('日本語タイトルと、選択したDLsiteメタデータ言語のタイトルを表示します。日本語が選択されている場合、ローカライズされたタイトルは非表示になります。')
            ->assertSee('選択したDLsiteメタデータ言語の説明を表示します。日本語が選択されている場合、このフィールドは非表示になります。')
            ->assertSee('aria-label="更新日をドラッグ"', false)
            ->assertSee('編集可能');

        $this->get(route('options.index', ['tab' => 'refetch']))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('作品を再取得')
            ->assertSee('再取得データをクリーンアップ')
            ->assertSee('aria-label="再取得のクリーンアップについて"', false)
            ->assertSee('作品IDまたはタイトルで検索...')
            ->assertSee('value="selected"', false);
    }

    public function test_refetch_tag_review_and_apply_ignore_order_only_locales(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create(['work_name' => 'ORDER_INSENSITIVE_TAG_WORK']);
        $oldJapanese = Genre::resolveByTitle('Old Japanese Tag');
        $englishAlpha = Genre::resolveByTitle('English Alpha');
        $englishBeta = Genre::resolveByTitle('English Beta');
        app(ProductGenreSync::class)->sync($product, [
            'ja' => [$oldJapanese->getKey()],
            'en' => [$englishAlpha->getKey(), $englishBeta->getKey()],
        ], []);

        $changes = app(RefetchDiffBuilder::class)->build(
            $product,
            new DLSiteFetchResult(
                workData: DLSiteWorkData::fromArray(['locales' => [
                    'ja' => [
                        'product_id' => $product->getKey(),
                        'genre' => ['New Japanese Tag'],
                    ],
                    'en' => [
                        'genre' => ['English Beta', 'English Alpha'],
                    ],
                ]]),
                failedImages: [],
            ),
            null,
        );
        $this->assertSame(['ja'], data_get($changes, 'tags.tags.details.changed_locales'));

        $run = app(RefetchService::class)->createRun([$product->getKey()], false);
        Storage::disk('local')->put(
            "Refetch/{$run->id}/Works/{$product->getKey()}.json",
            json_encode([
                'locales' => [
                    'ja' => [
                        'product_id' => $product->getKey(),
                        'work_name' => $product->work_name,
                        'genre' => ['New Japanese Tag'],
                    ],
                    'en' => [
                        'genre' => ['English Beta', 'English Alpha'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        );
        $result = $run->results()->firstOrFail();
        $result->forceFill([
            'status' => RefetchWorkResult::STATUS_FETCHED,
            'changes' => $changes,
        ])->save();
        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'completed_at' => now(),
            'resolved_tabs' => collect(RefetchCategory::cases())
                ->reject(fn(RefetchCategory $category): bool => $category === RefetchCategory::Tags)
                ->map->value
                ->values()
                ->all(),
        ])->save();

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->call('showCategory', RefetchCategory::Tags->value)
            ->assertSee('New Japanese Tag')
            ->assertDontSee('English Alpha')
            ->assertDontSee('English Beta');

        app(RefetchService::class)->applyTab(
            $run->fresh(),
            RefetchCategory::Tags,
            RefetchService::ACTION_OVERWRITE,
        );

        $this->assertSame(['New Japanese Tag'], $product->fetchedGenresForLanguage(DlsiteLanguage::Japanese)->pluck('genres.title')->all());
        $this->assertEqualsCanonicalizing(
            ['English Alpha', 'English Beta'],
            $product->fetchedGenresForLanguage(DlsiteLanguage::English)->pluck('genres.title')->all(),
        );
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
                        'old' => [
                            'fetched' => ['ja' => ['RAW_STALE_JP_TOKEN'], 'en' => []],
                            'custom' => ['RAW_CUSTOM_TAG_TOKEN'],
                        ],
                        'new' => [
                            'fetched' => ['ja' => ['RAW_JP_TAG_TOKEN'], 'en' => ['RAW_EN_TAG_TOKEN']],
                            'custom' => ['RAW_CUSTOM_TAG_TOKEN'],
                        ],
                        'details' => [
                            'changed_locales' => ['ja', 'en'],
                            'added' => ['ja' => ['RAW_JP_TAG_TOKEN'], 'en' => ['RAW_EN_TAG_TOKEN']],
                            'stale' => ['ja' => ['RAW_STALE_JP_TOKEN'], 'en' => []],
                            'custom_to_fetched' => ['ja' => ['RAW_CUSTOM_TAG_TOKEN'], 'en' => []],
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
            ->assertSee('<title>DLsite情報を再取得</title>', false)
            ->assertSee('確認')
            ->assertSee('タイトル')
            ->assertSee('すべてを「上書き」に設定')
            ->assertSee('変更がある未処理の各タブの全体選択を「上書き」に設定します。')
            ->assertSee('すべてのタブを適用')
            ->assertSee('RAW_WORK_TITLE_TOKEN')
            ->assertSee('RAW_NEW_TITLE_TOKEN')
            ->assertSee('UPSTREAM_RAW_ERROR_TOKEN');

        App::setLocale(UiLanguage::Japanese->value);

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->call('showCategory', RefetchCategory::Tags->value)
            ->assertSee('日本語')
            ->assertSee('追加')
            ->assertSee('取得されなくなったタグ')
            ->assertDontSee('取得タグとして返されたカスタムタグ')
            ->assertSee('RAW_JP_TAG_TOKEN');

        $tagResultId = $run->results()->where('product_id', $fetchedProduct->id)->value('id');
        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->call('showCategory', RefetchCategory::Tags->value)
            ->set("actions.tags.{$tagResultId}.ja", RefetchService::ACTION_DETAILED)
            ->assertSee('新しいタグ')
            ->assertSee('取得されなくなったタグ')
            ->assertSee('取得タグとして返されたカスタムタグ')
            ->assertSee('RAW_CUSTOM_TAG_TOKEN')
            ->assertSee('取得済みタグとして追加')
            ->assertSee('カスタムタグに変更')
            ->assertSee('value="move_to_custom"', false);

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->call('askApplyAll')
            ->assertSee('未処理のすべてのタブの選択を適用しますか？')
            ->assertSee('すべてのタブを適用')
            ->assertSee('すべてのタブを適用しています...')
            ->assertSee('キャンセル');

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->call('askRejectOrFinish')
            ->assertSee('この再取得を拒否しますか？')
            ->assertSee('再取得を拒否')
            ->assertSee('再取得を拒否しています...')
            ->assertSee('キャンセル');
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

        Livewire::test(OptionsResetDefaults::class, ['activeTab' => 'display'])
            ->call('resetAll')
            ->assertRedirectToRoute('options.index', ['tab' => 'display']);

        $this->assertSame('All Options settings reset to defaults.', session('options_reset_notice'));
        $this->assertSame(UiLanguage::English, Option::uiLanguage());

        $this->get(route('options.index', ['tab' => 'display']))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('All Options settings reset to defaults.')
            ->assertSee('href="/options?tab=display"', false);
    }
}
