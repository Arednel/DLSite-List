<?php

namespace Tests\Feature;

use App\Enums\ProductProgress;
use App\Enums\UiLanguage;
use App\Livewire\ProductIndex;
use App\Models\Option;
use App\Models\Product;
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
            ->assertSee('<html lang="en">', false);
        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('<html lang="en"', false);
        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('<html lang="en"', false);

        $completed = view('WorkFormCompleted', ['redirectUrl' => '/'])->render();

        $this->assertStringContainsString('<html lang="en">', $completed);
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
            ->assertSee('<html lang="ja">', false)
            ->assertSee('USER_WORK_NAME_TOKEN')
            ->assertSee('href="/?progress=Listening"', false)
            ->assertSee('placeholder="検索…"', false)
            ->assertSee('aria-label="検索"', false)
            ->assertSee('data-label="タイトル"', false)
            ->assertSee('data-work-form-modal-title="作品を編集"', false)
            ->getContent();

        $this->assertStringContainsString('聴取中', $html);
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
            ->assertSee('カスタム作品を追加')
            ->assertSee('placeholder="カンマ区切りで入力します。カンマを含むタグは二重引用符で囲んでください。例: &quot;Junior / Senior (at work, school, etc)&quot;, Office Lady"', false)
            ->assertSee('今日の日付を入力');

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('<html lang="ja"', false)
            ->assertSee('<title>編集</title>', false)
            ->assertSee('作品を編集')
            ->assertSee('この作品を削除してもよろしいですか？')
            ->assertSee('削除する');
    }

    public function test_japanese_modal_completion_localizes_fallback_copy_and_preserves_redirect_target(): void
    {
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
            ->assertSee('<title>作品を保存しました</title>', false)
            ->assertSee('作品を保存しました')
            ->assertSee('>続行</a>', false)
            ->assertSee("redirectUrl: '\\/#{$workId}'", false);
    }

    public function test_japanese_custom_validation_is_localized_while_framework_validation_stays_english(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);

        $this->from(route('products.create.custom'))
            ->post(route('products.store.custom'), [])
            ->assertRedirect(route('products.create.custom'))
            ->assertSessionHasErrors([
                'id' => 'RJコードまたはRJコードを含むDLSiteリンクを入力してください。',
                'work_name' => 'The work name field is required.',
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

    public function test_translation_catalogs_have_matching_keys_placeholders_and_supported_english_overrides(): void
    {
        $english = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $japanese = json_decode(file_get_contents(lang_path('ja.json')), true, 512, JSON_THROW_ON_ERROR);
        $englishDisplayOverrides = [
            'Fetched Language Tags' => 'Fetched EN Tags',
            'Custom->Fetched' => 'Custom -> Fetched',
            'Custom->Fetched JP' => 'Custom -> Fetched JP',
            'Custom->Fetched EN' => 'Custom -> Fetched EN',
            'This work Custom->Fetched' => 'This work Custom -> Fetched',
        ];
        $actualEnglishOverrides = [];
        $placeholders = static function (string $value): array {
            preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
            sort($matches[0]);

            return $matches[0];
        };

        $this->assertEqualsCanonicalizing(array_keys($english), array_keys($japanese));

        foreach ($english as $key => $value) {
            if ($key !== $value) {
                $actualEnglishOverrides[$key] = $value;
            }

            $this->assertNotSame('', trim($japanese[$key]));
            $this->assertSame($placeholders($key), $placeholders($japanese[$key]));
        }

        $this->assertSame($englishDisplayOverrides, $actualEnglishOverrides);
    }
}
