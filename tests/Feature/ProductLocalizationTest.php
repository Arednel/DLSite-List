<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductLocalization;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_keeps_japanese_title_on_product_and_moves_english_title_to_localization(): void
    {
        $product = Product::factory()->withTitles('Migrated Japanese title', 'Migrated English title')->create([
            'id' => 'RJ123456780',
        ]);

        $migration = $this->migration();
        $migration->down();

        $this->assertFalse(Schema::hasTable('product_localizations'));
        $this->assertTrue(Schema::hasColumn('products', 'work_name'));
        $this->assertTrue(Schema::hasColumn('products', 'work_name_english'));
        $this->assertSame('Migrated Japanese title', DB::table('products')->where('id', $product->id)->value('work_name'));
        $this->assertSame('Migrated English title', DB::table('products')->where('id', $product->id)->value('work_name_english'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('products', 'work_name'));
        $this->assertFalse(Schema::hasColumn('products', 'work_name_english'));
        $this->assertSame('Migrated Japanese title', DB::table('products')->where('id', $product->id)->value('work_name'));
        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => ProductLocalization::LOCALE_JAPANESE,
        ]);
        $this->assertDatabaseHas('product_localizations', [
            'product_id' => $product->id,
            'locale' => ProductLocalization::LOCALE_ENGLISH,
            'title' => 'Migrated English title',
        ]);
    }

    public function test_migration_skips_blank_english_titles(): void
    {
        $product = Product::factory()->withEnglishTitle(null)->create([
            'id' => 'RJ123456781',
            'work_name' => 'Japanese title',
        ]);

        $migration = $this->migration();
        $migration->down();

        DB::table('products')->where('id', $product->id)->update([
            'work_name_english' => '   ',
        ]);

        $migration->up();

        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => ProductLocalization::LOCALE_ENGLISH,
        ]);
    }

    public function test_title_reads_use_product_for_japanese_and_localization_rows_for_translations(): void
    {
        $product = Product::factory()->withTitles('Japanese title', 'English title')->create();

        $this->assertSame('Japanese title', $product->titleForLocale(ProductLocalization::LOCALE_JAPANESE));
        $this->assertSame('English title', $product->titleForLocale(ProductLocalization::LOCALE_ENGLISH));
        $this->assertNull($product->titleForLocale('zh_TW'));
    }

    public function test_unsaved_product_can_read_its_japanese_title_but_not_translations(): void
    {
        $product = new Product(['work_name' => 'Unsaved Japanese title']);

        $this->assertSame('Unsaved Japanese title', $product->titleForLocale(ProductLocalization::LOCALE_JAPANESE));
        $this->assertNull($product->titleForLocale(ProductLocalization::LOCALE_ENGLISH));
    }

    public function test_sync_localization_supports_arbitrary_translation_locales(): void
    {
        $product = Product::factory()->create();

        $changed = $product->syncLocalization('zh_TW', [
            'title' => '繁體中文標題',
        ]);

        $this->assertTrue($changed);
        $this->assertSame('繁體中文標題', $product->titleForLocale('zh_TW'));
        $this->assertDatabaseHas('product_localizations', [
            'product_id' => $product->id,
            'locale' => 'zh_TW',
            'title' => '繁體中文標題',
        ]);
    }

    public function test_translation_changes_touch_the_parent_product_timestamp(): void
    {
        try {
            Carbon::setTestNow('2026-11-10 10:00:00');
            $product = Product::factory()->withEnglishTitle('Old English title')->create()->fresh();

            Carbon::setTestNow('2026-11-10 10:00:01');
            $changed = $product->syncLocalization(
                ProductLocalization::LOCALE_ENGLISH,
                ['title' => 'New English title'],
            );

            $this->assertTrue($changed);
            $this->assertSame(
                '2026-11-10 10:00:01',
                $product->fresh()->updated_at->format('Y-m-d H:i:s'),
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_unchanged_translation_does_not_touch_the_parent_product_timestamp(): void
    {
        try {
            Carbon::setTestNow('2026-11-10 10:00:00');
            $product = Product::factory()->withEnglishTitle('English title')->create()->fresh();
            $updatedAt = $product->updated_at;

            Carbon::setTestNow('2026-11-10 10:00:01');
            $changed = $product->syncLocalization(
                ProductLocalization::LOCALE_ENGLISH,
                ['title' => 'English title'],
            );

            $this->assertFalse($changed);
            $this->assertTrue($product->fresh()->updated_at->equalTo($updatedAt));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_sync_localization_does_not_create_a_row_for_empty_optional_data(): void
    {
        $product = Product::factory()->withEnglishTitle(null)->create();

        $changed = $product->syncLocalization(
            ProductLocalization::LOCALE_ENGLISH,
            ['title' => null],
        );

        $this->assertFalse($changed);
        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => ProductLocalization::LOCALE_ENGLISH,
        ]);
    }

    public function test_sync_localization_rejects_japanese_locale(): void
    {
        $product = Product::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $product->syncLocalization(
            ProductLocalization::LOCALE_JAPANESE,
            ['title' => 'Japanese title'],
        );
    }

    public function test_sync_localization_rejects_locale_identity_in_attributes(): void
    {
        $product = Product::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $product->syncLocalization(
            ProductLocalization::LOCALE_ENGLISH,
            [
                'locale' => 'fr',
                'title' => 'English title',
            ],
        );
    }

    public function test_sync_localization_rejects_unsaved_product(): void
    {
        $this->expectException(\LogicException::class);

        (new Product(['work_name' => 'Unsaved Japanese title']))->syncLocalization(
            ProductLocalization::LOCALE_ENGLISH,
            ['title' => 'English title'],
        );
    }

    public function test_empty_localization_deletes_itself_after_save(): void
    {
        $product = Product::factory()->withEnglishTitle('English title')->create();
        $localization = $product->localizations()
            ->where('locale', ProductLocalization::LOCALE_ENGLISH)
            ->firstOrFail();

        $localization->title = null;
        $localization->save();

        $this->assertNull($product->titleForLocale(ProductLocalization::LOCALE_ENGLISH));
        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => ProductLocalization::LOCALE_ENGLISH,
        ]);
    }

    public function test_clearing_last_localized_field_deletes_row_and_touches_parent(): void
    {
        try {
            Carbon::setTestNow('2026-11-10 10:00:00');
            $product = Product::factory()->withEnglishTitle('English title')->create()->fresh();

            Carbon::setTestNow('2026-11-10 10:00:01');
            $changed = $product->syncLocalization(
                ProductLocalization::LOCALE_ENGLISH,
                ['title' => null],
            );

            $this->assertTrue($changed);
            $this->assertDatabaseMissing('product_localizations', [
                'product_id' => $product->id,
                'locale' => ProductLocalization::LOCALE_ENGLISH,
            ]);
            $this->assertSame(
                '2026-11-10 10:00:01',
                $product->fresh()->updated_at->format('Y-m-d H:i:s'),
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_blank_localization_created_directly_is_removed_by_model_invariant(): void
    {
        $product = Product::factory()->withEnglishTitle(null)->create();

        $product->localizations()->create([
            'locale' => ProductLocalization::LOCALE_ENGLISH,
            'title' => '   ',
        ]);

        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => ProductLocalization::LOCALE_ENGLISH,
        ]);
    }

    public function test_localization_is_empty_when_all_current_localized_fields_are_blank(): void
    {
        $localization = new ProductLocalization(['title' => null]);
        $this->assertTrue($localization->isEmpty());

        $localization->title = '';
        $this->assertTrue($localization->isEmpty());

        $localization->title = '   ';
        $this->assertTrue($localization->isEmpty());

        $localization->title = 'Translated title';
        $this->assertFalse($localization->isEmpty());
    }

    public function test_current_title_search_uses_canonical_japanese_and_only_current_translated_search_locales(): void
    {
        $product = Product::factory()->withTitles('Japanese searchable title', 'English searchable title')->create();
        $product->syncLocalization('zh_TW', [
            'title' => 'Future locale only token',
        ]);

        $this->assertTrue(Product::query()->filterTitle('Japanese searchable')->whereKey($product->getKey())->exists());
        $this->assertTrue(Product::query()->filterTitle('English searchable')->whereKey($product->getKey())->exists());
        $this->assertFalse(Product::query()->filterTitle('Future locale only')->whereKey($product->getKey())->exists());
        $this->assertFalse(Product::query()->searchIndex('Future locale only')->whereKey($product->getKey())->exists());
    }

    public function test_global_search_returns_each_product_once_and_retains_other_search_fields(): void
    {
        $both = Product::factory()->withTitles('Shared token Japanese', 'Shared token English')->create();
        $notes = Product::factory()->create([
            'work_name' => 'Unrelated title',
            'notes' => 'Shared token notes',
        ]);
        $unmatched = Product::factory()->create(['work_name' => 'Another title']);
        $unmatched->syncLocalization('fr', [
            'title' => 'Shared token French',
        ]);

        $this->assertEqualsCanonicalizing(
            [$both->id, $notes->id],
            Product::query()->searchIndex('Shared token', [], false)->pluck('id')->all(),
        );
        $this->assertSame([], Product::query()->searchIndex('No matching title token', [], false)->pluck('id')->all());
    }

    public function test_deleting_product_cascades_localizations(): void
    {
        $product = Product::factory()->create();

        $product->delete();

        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
        ]);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_09_162900_move_product_titles_to_localizations.php');
    }
}
