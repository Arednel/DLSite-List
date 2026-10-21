<?php

namespace Tests\Feature;

use App\Enums\ContentFocus;
use App\Enums\ProductContributorRole;
use App\Enums\ProductField;
use App\Enums\ProductProgress;
use App\Enums\UiLanguage;
use App\Livewire\ProductIndex;
use App\Models\Option;
use App\Models\Product;
use App\Support\ProductContributorSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSurfaceLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_english_shells_declare_the_active_locale(): void
    {
        $product = Product::factory()->create();

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('<html lang="en"', false);
        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('<html lang="en"', false);
        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('<html lang="en"', false);

        $completed = view('WorkFormCompleted', ['redirectUrl' => '/'])->render();

        $this->assertStringContainsString('<html lang="en">', $completed);
    }

    public function test_general_content_focus_changes_ui_wording_without_mutating_internal_values(): void
    {
        Option::setContentFocus(ContentFocus::General);
        $product = Product::factory()->create([
            'progress' => ProductProgress::Listening->value,
        ]);

        $html = $this->get('/?progress=Listening')
            ->assertOk()
            ->assertSee('href="/?progress=Listening"', false)
            ->assertSee('All Works')
            ->assertSee('Currently In Progress')
            ->assertSee('Planned')
            ->assertSee('Total Times Repeated')
            ->assertSee('Repeat Value')
            ->assertSee('(6) Fine')
            ->assertSee('(4) Bad')
            ->assertSee('(3) Very Bad')
            ->assertSee('(2) Horrible')
            ->assertSee('(1) Appalling')
            ->assertDontSee('All ASMR')
            ->assertDontSee('Currently Listening')
            ->getContent();

        $this->assertStringContainsString('In Progress', $html);
        $this->assertSame(ProductProgress::Listening->value, $product->refresh()->progress);
    }

    public function test_index_progress_column_uses_selected_ui_wording(): void
    {
        Option::setContentFocus(ContentFocus::General);
        Product::factory()->create([
            'progress' => ProductProgress::PlanToListen->value,
        ]);

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('<span>Planned</span>', false)
            ->assertDontSee('<span>Plan to Listen</span>', false);
    }

    public function test_saved_japanese_localizes_index_copy_accessibility_and_context_without_mutating_values(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);
        $product = Product::factory()->create([
            'work_name' => 'USER_WORK_NAME_TOKEN',
            'progress' => ProductProgress::Listening->value,
        ]);

        $html = $this->get('/?progress=Listening')
            ->assertOk()
            ->assertSee('<html lang="ja"', false)
            ->assertSee('USER_WORK_NAME_TOKEN')
            ->assertSee('href="/?progress=Listening"', false)
            ->assertSee('placeholder="検索..."', false)
            ->assertSee('aria-label="検索"', false)
            ->assertSee('data-label="タイトル"', false)
            ->assertSee('data-work-form-modal-title="作品情報を編集"', false)
            ->getContent();

        $this->assertStringContainsString(ProductProgress::Listening->label(), $html);
        $this->assertSame(ProductProgress::Listening->value, $product->refresh()->progress);

        $this->get('/?search=NO_LOCALIZATION_MATCH')
            ->assertOk()
            ->assertSee('現在の絞り込み条件に一致する作品はありません。');
    }

    public function test_saved_japanese_localizes_create_edit_fields_tooltips_and_delete_confirmation(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);
        $product = Product::factory()->create();

        $this->get(route('products.create.custom'))
            ->assertOk()
            ->assertSee('<html lang="ja"', false)
            ->assertSee('<title>追加</title>', false)
            ->assertSee('手動で追加')
            ->assertSee('placeholder="カンマ区切りで入力します。カンマを含むタグは二重引用符で囲んでください。例: &quot;Junior / Senior (at work, school, etc)&quot;, Office Lady"', false)
            ->assertSee('今日の日付を入力');

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('<html lang="ja"', false)
            ->assertSee('<title>編集</title>', false)
            ->assertSee('作品情報を編集')
            ->assertSee('この作品を削除してもよろしいですか？')
            ->assertSee('削除する');
    }

    public function test_japanese_ui_preserves_library_content_values_verbatim(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);
        Option::setEditFieldLayout([
            ['field' => ProductField::Series->value, 'visible' => true, 'editable' => true],
            ['field' => ProductField::Title->value, 'visible' => true, 'editable' => true],
            ['field' => ProductField::Circle->value, 'visible' => true, 'editable' => true],
            ['field' => ProductField::Scenario->value, 'visible' => true, 'editable' => true],
            ['field' => ProductField::VoiceActor->value, 'visible' => true, 'editable' => true],
            ['field' => ProductField::Illustration->value, 'visible' => true, 'editable' => true],
            ['field' => ProductField::Author->value, 'visible' => true, 'editable' => true],
        ]);
        App::setLocale(UiLanguage::Japanese->value);
        $product = Product::factory()->create([
            'work_name' => 'Errors',
            'work_name_english' => 'Apply All',
            'series' => 'Site ID',
            'circle' => 'Archive parts',
        ]);
        app(ProductContributorSync::class)->sync($product, [
            ProductContributorRole::Scenario->value => ['Data'],
            ProductContributorRole::VoiceActor->value => ['Images'],
            ProductContributorRole::Illustration->value => ['Works'],
            ProductContributorRole::Author->value => ['Options'],
        ], null);

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('>Errors</textarea>', false)
            ->assertSee('>Apply All</textarea>', false)
            ->assertSee('>Site ID</textarea>', false)
            ->assertSee('value="Archive parts"', false)
            ->assertSee('>Data</textarea>', false)
            ->assertSee('>Images</textarea>', false)
            ->assertSee('>Works</textarea>', false)
            ->assertSee('>Options</textarea>', false);
    }

    public function test_japanese_modal_completion_localizes_fallback_copy_and_preserves_redirect_target(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Option::setUiLanguage(UiLanguage::Japanese);
        $workId = Product::factory()->make()->id;

        $response = $this->post(route('products.store.custom'), [
            'id' => $workId,
            'work_name' => 'COMPLETION_USER_TOKEN',
            'age_category' => 'ALL_AGES',
            'work_image' => UploadedFile::fake()->image('cover.png'),
            'modal' => '1',
        ]);

        $response->assertOk()
            ->assertViewIs('WorkFormCompleted')
            ->assertViewHas('redirectUrl', "/#{$workId}")
            ->assertSee('<html lang="ja">', false)
            ->assertSee('<title>保存しました</title>', false)
            ->assertSee('変更を保存しました')
            ->assertSee('>続行</a>', false)
            ->assertSee("redirectUrl: '\\/#{$workId}'", false);

        $this->assertSame("storage/Works/{$workId}/cover.png", Product::findOrFail($workId)->work_image);
        Storage::disk('public')->assertExists("Works/{$workId}/cover.png");
    }

    public function test_japanese_custom_and_framework_validation_are_localized(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);

        $this->from(route('products.create.custom'))
            ->post(route('products.store.custom'), [])
            ->assertRedirect(route('products.create.custom'))
            ->assertSessionHasErrors([
                'id' => '作品コードまたは作品コードを含むリンクを入力してください。',
                'work_name' => '日本語タイトルは必須項目です。',
            ]);

        $this->from(route('products.create'))
            ->post(route('products.store'), [
                'id' => 'RJ987654321',
                'add' => [
                    'start_date' => [
                        'month' => '02',
                        'day' => '30',
                        'year' => '2025',
                    ],
                ],
            ])
            ->assertRedirect(route('products.create'))
            ->assertSessionHasErrors([
                'add.start_date' => '開始日が正しくありません。',
            ]);
    }

    public function test_index_pagination_and_unlimited_count_use_localized_replacements_and_pluralization(): void
    {
        Product::factory()->count(2)->create();
        Option::setIndexPerPage(1);
        Option::setUiLanguage(UiLanguage::Japanese);
        App::setLocale(UiLanguage::Japanese->value);

        Livewire::test(ProductIndex::class)
            ->assertSee('aria-label="ページ送り"', false)
            ->assertSee('2件中1〜1件を表示')
            ->assertSee('前へ')
            ->assertSee('次へ')
            ->assertSee('aria-label="2ページへ移動"', false);
    }

    public function test_unlimited_english_count_uses_works_for_every_count(): void
    {
        Product::factory()->create();
        Option::setIndexPerPage(Option::INDEX_PER_PAGE_UNLIMITED);

        Livewire::test(ProductIndex::class)
            ->assertSee('Showing all 1 works');

        Product::factory()->create();

        Livewire::test(ProductIndex::class)
            ->assertSee('Showing all 2 works');
    }

    public function test_unlimited_japanese_count_uses_localized_text(): void
    {
        Product::factory()->count(2)->create();
        Option::setIndexPerPage(Option::INDEX_PER_PAGE_UNLIMITED);
        Option::setUiLanguage(UiLanguage::Japanese);
        App::setLocale(UiLanguage::Japanese->value);

        Livewire::test(ProductIndex::class)
            ->assertSee('全2件を表示');
    }

    public function test_missing_user_facing_labels_have_japanese_translations(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame('サイトID', __('Site ID'));
        $this->assertSame('エラー', __('Errors'));
        $this->assertSame('すべて適用', __('Apply All'));
        $this->assertSame(
            '一覧の「メモ」と「タグ」に高さ制限を設定します。制限を超える内容は「すべて表示」で展開できます。',
            __('Set height limits for Notes and Tags on the Index. Content that exceeds the limit can be expanded with ‘Show all’.'),
        );
        $this->assertSame(
            'DLsite作品データの取得でJSONファイルが作成されませんでした。',
            __('errors.DLsite work fetch did not create JSON.'),
        );
        $this->assertSame(
            'DLsite作品データの取得で無効なJSONが返されました。',
            __('errors.DLsite work fetch returned invalid JSON.'),
        );
        $this->assertSame(
            'DLsite作品データの取得で無効なマニフェストが返されました。',
            __('errors.DLsite work fetch returned an invalid manifest.'),
        );
        $this->assertSame(
            'DLsite作品データに作品IDがありません。',
            __('errors.DLsite work data is missing a product id.'),
        );
    }

    public function test_translation_catalogs_have_matching_keys_placeholders_and_supported_english_overrides(): void
    {
        $english = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $japanese = json_decode(file_get_contents(lang_path('ja.json')), true, 512, JSON_THROW_ON_ERROR);
        $englishErrors = require lang_path('en/errors.php');
        $japaneseErrors = require lang_path('ja/errors.php');
        $englishDisplayOverrides = [
            'Custom->Fetched' => 'Custom -> Fetched',
            'Custom->Fetched JP' => 'Custom -> Fetched JP',
            'Custom->Fetched EN' => 'Custom -> Fetched EN',
            'This work Custom->Fetched' => 'This work Custom -> Fetched',
            'Announcement Date from' => 'Scheduled release date from',
            'Announcement Date to' => 'Scheduled release date to',
            'Announcement Date' => 'Scheduled release date',
        ];
        $actualEnglishOverrides = [];
        $placeholders = static function (string $value): array {
            preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
            sort($matches[0]);

            return $matches[0];
        };

        $this->assertEqualsCanonicalizing(array_keys($english), array_keys($japanese));
        $this->assertArrayNotHasKey('errors', $english);
        $this->assertArrayNotHasKey('errors', $japanese);

        foreach ($english as $key => $value) {
            if ($key !== $value) {
                $actualEnglishOverrides[$key] = $value;
            }

            $this->assertNotSame('', trim($japanese[$key]));
            $this->assertSame($placeholders($key), $placeholders($japanese[$key]));
        }

        $this->assertSame($englishDisplayOverrides, $actualEnglishOverrides);

        $this->assertEqualsCanonicalizing(array_keys($englishErrors), array_keys($japaneseErrors));
        $this->assertSame([], array_values(array_intersect(array_keys($english), array_keys($englishErrors))));

        foreach ($englishErrors as $message => $value) {
            $this->assertNotSame('', trim($japaneseErrors[$message]));

            if ($message === 'login_throttled') {
                foreach (explode('|', $value) as $form) {
                    $this->assertSame($placeholders($form), $placeholders($japaneseErrors[$message]));
                }

                continue;
            }

            $this->assertSame($message, $value);
            $this->assertSame($placeholders($message), $placeholders($japaneseErrors[$message]));
        }
    }
}
