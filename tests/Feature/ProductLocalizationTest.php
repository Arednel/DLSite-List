<?php

namespace Tests\Feature;

use App\Enums\DlsiteLanguage;
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

    public function test_product_factory_creates_and_overrides_english_localization_using_dlsite_language(): void
    {
        $default = Product::factory()->create();

        $this->assertDatabaseHas('product_localizations', [
            'product_id' => $default->id,
            'locale' => DlsiteLanguage::English->value,
            'title' => 'WORK_EN_' . $default->id,
            'description' => 'Description English',
        ]);

        $overridden = Product::factory()
            ->withEnglishTitle('Factory English title')
            ->withEnglishDescription('Factory English description')
            ->create();

        $this->assertDatabaseHas('product_localizations', [
            'product_id' => $overridden->id,
            'locale' => DlsiteLanguage::English->value,
            'title' => 'Factory English title',
            'description' => 'Factory English description',
        ]);
    }

    public function test_migration_keeps_japanese_title_on_product_and_moves_english_title_to_localization(): void
    {
        $product = Product::factory()->withTitles('Migrated Japanese title', 'Migrated English title')->create([
            'id' => 'RJ123456780',
        ]);

        $descriptionMigration = $this->descriptionMigration();
        $descriptionMigration->down();
        $migration = $this->titleMigration();
        $migration->down();

        $this->assertFalse(Schema::hasTable('product_localizations'));
        $this->assertTrue(Schema::hasColumn('products', 'work_name'));
        $this->assertTrue(Schema::hasColumn('products', 'work_name_english'));
        $this->assertSame('Migrated Japanese title', DB::table('products')->where('id', $product->id)->value('work_name'));
        $this->assertSame('Migrated English title', DB::table('products')->where('id', $product->id)->value('work_name_english'));

        $migration->up();
        $descriptionMigration->up();

        $this->assertTrue(Schema::hasColumn('products', 'work_name'));
        $this->assertFalse(Schema::hasColumn('products', 'work_name_english'));
        $this->assertSame('Migrated Japanese title', DB::table('products')->where('id', $product->id)->value('work_name'));
        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::Japanese->value,
        ]);
        $this->assertDatabaseHas('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::English->value,
            'title' => 'Migrated English title',
        ]);
    }

    public function test_migration_skips_blank_english_titles(): void
    {
        $product = Product::factory()->withEnglishTitle(null)->withEnglishDescription(null)->create([
            'id' => 'RJ123456781',
            'work_name' => 'Japanese title',
        ]);

        $descriptionMigration = $this->descriptionMigration();
        $descriptionMigration->down();
        $migration = $this->titleMigration();
        $migration->down();

        DB::table('products')->where('id', $product->id)->update([
            'work_name_english' => '   ',
        ]);

        $migration->up();
        $descriptionMigration->up();

        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::English->value,
        ]);
    }

    public function test_description_migration_keeps_japanese_description_on_product_and_moves_english_description_to_existing_localization(): void
    {
        $product = Product::factory()
            ->withTitles('Japanese title', 'English title')
            ->withEnglishDescription('Migrated English description')
            ->create([
                'id' => 'RJ123456782',
                'description' => 'Migrated Japanese description',
            ]);

        $migration = $this->descriptionMigration();
        $migration->down();

        $this->assertTrue(Schema::hasColumn('products', 'description'));
        $this->assertTrue(Schema::hasColumn('products', 'description_english'));
        $this->assertFalse(Schema::hasColumn('product_localizations', 'description'));
        $this->assertSame('Migrated Japanese description', DB::table('products')->where('id', $product->id)->value('description'));
        $this->assertSame('Migrated English description', DB::table('products')->where('id', $product->id)->value('description_english'));

        $migration->up();

        $this->assertFalse(Schema::hasColumn('products', 'description_english'));
        $this->assertSame('Migrated Japanese description', DB::table('products')->where('id', $product->id)->value('description'));
        $this->assertDatabaseHas('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::English->value,
            'title' => 'English title',
            'description' => 'Migrated English description',
        ]);
    }

    public function test_description_migration_creates_localization_when_only_english_description_exists(): void
    {
        $product = Product::factory()
            ->withEnglishTitle(null)
            ->withEnglishDescription('English description')
            ->create([
                'id' => 'RJ123456783',
                'description' => 'Japanese description',
            ]);

        $migration = $this->descriptionMigration();
        $migration->down();

        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::English->value,
        ]);
        $this->assertSame(
            'English description',
            DB::table('products')->where('id', $product->id)->value('description_english'),
        );

        $migration->up();

        $this->assertDatabaseHas('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::English->value,
            'title' => null,
            'description' => 'English description',
        ]);
    }

    public function test_description_migration_rollback_restores_descriptions_and_cleans_blank_titles_across_batches(): void
    {
        $products = [];
        $localizations = [];
        $descriptions = [];
        $blankTitles = [null, '', '   ', "\t\n"];

        for ($index = 0; $index < 501; $index++) {
            $id = 'RJ' . (123460000 + $index);
            $products[] = ['id' => $id, 'work_name' => 'Japanese title'];
            $descriptions[$id] = 'English description ' . $index;
            $localizations[] = [
                'product_id' => $id,
                'locale' => 'en',
                'title' => $blankTitles[$index % count($blankTitles)],
                'description' => $descriptions[$id],
            ];
        }

        $preservedTitles = ['fr' => '  French title  ', 'zh_TW' => '0'];
        foreach ([...$preservedTitles, 'de' => "\t\n"] as $locale => $title) {
            $localizations[] = [
                'product_id' => $products[0]['id'],
                'locale' => $locale,
                'title' => $title,
                'description' => 'Other localized description',
            ];
        }

        DB::table('products')->insert($products);
        DB::table('product_localizations')->insert($localizations);
        $migration = $this->descriptionMigration();

        $migration->down();

        $this->assertSame(
            $descriptions,
            DB::table('products')->orderBy('id')->pluck('description_english', 'id')->all(),
        );
        $this->assertSame(
            $preservedTitles,
            DB::table('product_localizations')->orderBy('locale')->pluck('title', 'locale')->all(),
        );

        $migration->up();

        $this->assertSame(
            $descriptions,
            DB::table('product_localizations')->where('locale', 'en')
                ->orderBy('product_id')->pluck('description', 'product_id')->all(),
        );
        $this->assertSame(
            $preservedTitles,
            DB::table('product_localizations')->where('locale', '!=', 'en')
                ->orderBy('locale')->pluck('title', 'locale')->all(),
        );
    }

    public function test_japanese_metadata_stays_on_the_product_and_translations_are_resolved_by_locale(): void
    {
        $product = Product::factory()
            ->withTitles('Japanese title', 'English title')
            ->withEnglishDescription('English description')
            ->create(['description' => 'Japanese description']);
        $product->syncLocalization(DlsiteLanguage::TraditionalChinese, [
            'title' => 'Traditional Chinese title',
            'description' => 'Traditional Chinese description',
        ]);

        $englishLocalization = $product->localizations()->where('locale', DlsiteLanguage::English->value)->firstOrFail();
        $chineseLocalization = $product->localizations()->where('locale', DlsiteLanguage::TraditionalChinese->value)->firstOrFail();

        $this->assertSame('Japanese title', $product->work_name);
        $this->assertSame('Japanese description', $product->description);
        $this->assertSame(DlsiteLanguage::English->value, $englishLocalization->locale);
        $this->assertSame('English title', $englishLocalization->title);
        $this->assertSame('English description', $englishLocalization->description);
        $this->assertSame('Traditional Chinese title', $chineseLocalization->title);
        $this->assertSame('Traditional Chinese description', $chineseLocalization->description);
        $this->assertFalse($product->localizations()->where('locale', DlsiteLanguage::Japanese->value)->exists());
    }

    public function test_sync_localization_supports_arbitrary_translation_locales(): void
    {
        $product = Product::factory()->create();

        $changed = $product->syncLocalization('fr', [
            'title' => 'French title',
        ]);

        $this->assertTrue($changed);
        $this->assertDatabaseHas('product_localizations', [
            'product_id' => $product->id,
            'locale' => 'fr',
            'title' => 'French title',
        ]);
    }

    public function test_translation_changes_touch_the_parent_product_timestamp(): void
    {
        try {
            Carbon::setTestNow('2026-11-10 10:00:00');
            $product = Product::factory()->withEnglishTitle('Old English title')->create()->fresh();

            Carbon::setTestNow('2026-11-10 10:00:01');
            $changed = $product->syncLocalization(
                DlsiteLanguage::English->value,
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
                DlsiteLanguage::English->value,
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
        $product = Product::factory()->withEnglishTitle(null)->withEnglishDescription(null)->create();

        $changed = $product->syncLocalization(
            DlsiteLanguage::English->value,
            ['title' => null],
        );

        $this->assertFalse($changed);
        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::English->value,
        ]);
    }

    public function test_sync_localization_rejects_japanese_locale(): void
    {
        $product = Product::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $product->syncLocalization(
            DlsiteLanguage::Japanese->value,
            ['title' => 'Japanese title'],
        );
    }

    public function test_sync_localization_rejects_locale_identity_in_attributes(): void
    {
        $product = Product::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $product->syncLocalization(
            DlsiteLanguage::English->value,
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
            DlsiteLanguage::English->value,
            ['title' => 'English title'],
        );
    }

    public function test_empty_localization_deletes_itself_after_save(): void
    {
        $product = Product::factory()->withEnglishTitle('English title')->withEnglishDescription(null)->create();
        $localization = $product->localizations()
            ->where('locale', DlsiteLanguage::English->value)
            ->firstOrFail();

        $localization->title = null;
        $localization->save();

        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::English->value,
        ]);
    }

    public function test_clearing_last_localized_field_deletes_row_and_touches_parent(): void
    {
        try {
            Carbon::setTestNow('2026-11-10 10:00:00');
            $product = Product::factory()->withEnglishTitle('English title')->withEnglishDescription(null)->create()->fresh();

            Carbon::setTestNow('2026-11-10 10:00:01');
            $changed = $product->syncLocalization(
                DlsiteLanguage::English->value,
                ['title' => null],
            );

            $this->assertTrue($changed);
            $this->assertDatabaseMissing('product_localizations', [
                'product_id' => $product->id,
                'locale' => DlsiteLanguage::English->value,
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
        $product = Product::factory()->withEnglishTitle(null)->withEnglishDescription(null)->create();

        $product->localizations()->create([
            'locale' => DlsiteLanguage::English->value,
            'title' => '   ',
        ]);

        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::English->value,
        ]);
    }

    public function test_localization_is_empty_when_all_current_localized_fields_are_blank(): void
    {
        $localization = new ProductLocalization(['title' => null, 'description' => null]);
        $this->assertTrue($localization->isEmpty());

        $localization->title = '';
        $this->assertTrue($localization->isEmpty());

        $localization->title = '   ';
        $this->assertTrue($localization->isEmpty());

        $localization->title = 'Translated title';
        $this->assertFalse($localization->isEmpty());

        $localization->title = null;
        $localization->description = 'Translated description';
        $this->assertFalse($localization->isEmpty());
    }

    public function test_localization_row_is_kept_until_both_title_and_description_are_empty(): void
    {
        $product = Product::factory()
            ->withEnglishTitle('English title')
            ->withEnglishDescription('English description')
            ->create();

        $this->assertTrue($product->syncLocalization(
            DlsiteLanguage::English->value,
            ['title' => null],
        ));
        $this->assertDatabaseHas('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::English->value,
            'title' => null,
            'description' => 'English description',
        ]);

        $this->assertTrue($product->syncLocalization(
            DlsiteLanguage::English->value,
            ['description' => null],
        ));
        $this->assertDatabaseMissing('product_localizations', [
            'product_id' => $product->id,
            'locale' => DlsiteLanguage::English->value,
        ]);
    }

    public function test_title_filter_uses_canonical_japanese_and_requested_localization(): void
    {
        $product = Product::factory()->withTitles('Japanese searchable title', 'English searchable title')->create();
        $product->syncLocalization('fr', [
            'title' => 'Future locale only token',
        ]);

        $this->assertTrue(Product::query()
            ->filterTitle('Japanese searchable', DlsiteLanguage::English->value)
            ->whereKey($product->getKey())
            ->exists());
        $this->assertTrue(Product::query()
            ->filterTitle('English searchable', DlsiteLanguage::English->value)
            ->whereKey($product->getKey())
            ->exists());
        $this->assertFalse(Product::query()
            ->filterTitle('Future locale only', DlsiteLanguage::English->value)
            ->whereKey($product->getKey())
            ->exists());
        $this->assertTrue(Product::query()
            ->filterTitle('Future locale only', 'fr')
            ->whereKey($product->getKey())
            ->exists());
    }

    public function test_general_search_uses_canonical_japanese_and_all_stored_localized_titles(): void
    {
        $product = Product::factory()->withTitles('Japanese searchable title', 'English searchable title')->create();
        $product->syncLocalization('fr', [
            'title' => 'Future locale only token',
        ]);

        foreach (['Japanese searchable', 'English searchable', 'Future locale only'] as $search) {
            $this->assertTrue(Product::query()
                ->searchIndex($search, [], false, DlsiteLanguage::Japanese->value)
                ->whereKey($product->getKey())
                ->exists());
        }
    }

    public function test_general_search_remains_grouped_with_filters_applied_before_it(): void
    {
        $included = Product::factory()->create([
            'work_name' => 'GROUPED_SEARCH_TOKEN canonical match',
            'progress' => 'Completed',
        ]);
        Product::factory()->withEnglishTitle('GROUPED_SEARCH_TOKEN localized match')->create([
            'work_name' => 'Unrelated canonical title',
            'progress' => 'Listening',
        ]);

        $this->assertSame(
            [$included->id],
            Product::query()
                ->where('progress', 'Completed')
                ->searchIndex('GROUPED_SEARCH_TOKEN', [], false, DlsiteLanguage::English->value)
                ->pluck('id')
                ->all(),
        );
    }

    public function test_general_search_remains_grouped_with_constraints_applied_after_it(): void
    {
        $target = Product::factory()->create([
            'work_name' => 'Unrelated target title',
        ]);
        Product::factory()->create([
            'work_name' => 'LATER_CONSTRAINT_SEARCH_TOKEN',
        ]);

        $this->assertFalse(Product::query()
            ->searchIndex('LATER_CONSTRAINT_SEARCH_TOKEN', [], false, DlsiteLanguage::English->value)
            ->whereKey($target->getKey())
            ->exists());
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
            [$both->id, $notes->id, $unmatched->id],
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

    private function titleMigration(): Migration
    {
        return require database_path('migrations/2026_11_09_162900_move_product_titles_to_localizations.php');
    }

    private function descriptionMigration(): Migration
    {
        return require database_path('migrations/2026_11_11_000000_move_product_descriptions_to_localizations.php');
    }
}
