<?php

namespace Tests\Unit\Support;

use App\Enums\UiLanguage;
use App\Models\Genre;
use App\Models\Product;
use App\Support\ProductGenreSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductGenreSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_one_fetched_genre_with_multiple_language_rows(): void
    {
        $product = Product::factory()->create();
        $genre = Genre::query()->create([
            'title' => 'ASMR',
        ]);

        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_JAPANESE => [$genre->getKey()],
            Genre::LANGUAGE_ENGLISH => [$genre->getKey()],
        ], []);

        $pivot = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('genre_id', $genre->getKey())
            ->first();

        $this->assertSame(Genre::PIVOT_SOURCE_FETCHED, $pivot->source);
        $this->assertEqualsCanonicalizing(
            [Genre::LANGUAGE_JAPANESE, Genre::LANGUAGE_ENGLISH],
            DB::table('genre_product_languages')
                ->where('genre_product_id', $pivot->id)
                ->pluck('language')
                ->all()
        );
    }

    public function test_custom_sync_preserves_existing_fetched_language_rows(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $product = Product::factory()->create();
        $englishGenre = Genre::query()->create(['title' => 'English Fetched']);
        $japaneseGenre = Genre::query()->create(['title' => 'Japanese Fetched']);
        $customGenre = Genre::query()->create(['title' => 'Custom']);
        $sync = app(ProductGenreSync::class);

        $sync->sync($product, [
            Genre::LANGUAGE_ENGLISH => [$englishGenre->getKey()],
            Genre::LANGUAGE_JAPANESE => [$japaneseGenre->getKey()],
        ], []);

        $changed = $sync->syncCustom($product, [$customGenre->getKey()]);

        $this->assertTrue($changed);
        $product->refresh()->load(['englishGenres', 'japaneseGenres', 'customGenres']);
        $this->assertSame(['English Fetched'], $product->englishGenres->pluck('title')->all());
        $this->assertSame(['Japanese Fetched'], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['Custom'], $product->customGenres->pluck('title')->all());
    }

    public function test_custom_tag_changes_touch_the_product_but_an_unchanged_sync_does_not(): void
    {
        $product = Product::factory()->create();
        $genre = Genre::query()->create(['title' => 'Timestamp Tag']);
        $sync = app(ProductGenreSync::class);
        $initialUpdatedAt = $product->updated_at;

        $this->travelTo($initialUpdatedAt->copy()->addMinute());

        $this->assertTrue($sync->syncCustom($product, [$genre->getKey()]));
        $addedUpdatedAt = $product->fresh()->updated_at;
        $this->assertTrue($addedUpdatedAt->greaterThan($initialUpdatedAt));

        $this->travelTo($addedUpdatedAt->copy()->addMinute());

        $this->assertFalse($sync->syncCustom($product, [$genre->getKey()]));
        $this->assertTrue($product->fresh()->updated_at->equalTo($addedUpdatedAt));

        $this->travelTo($addedUpdatedAt->copy()->addMinutes(2));

        $this->assertTrue($sync->syncCustom($product, []));
        $this->assertTrue($product->fresh()->updated_at->greaterThan($addedUpdatedAt));

        $this->travelBack();
    }

    public function test_editable_english_sync_replaces_english_and_preserves_japanese_and_custom_rows(): void
    {
        $product = Product::factory()->create();
        $japaneseGenre = Genre::query()->create(['title' => 'Japanese Only']);
        $oldEnglishGenre = Genre::query()->create(['title' => 'Old English']);
        $newEnglishGenre = Genre::query()->create(['title' => 'New English']);
        $customGenre = Genre::query()->create(['title' => 'Custom Preserved']);
        $sync = app(ProductGenreSync::class);

        $sync->sync($product, [
            Genre::LANGUAGE_JAPANESE => [$japaneseGenre->getKey()],
            Genre::LANGUAGE_ENGLISH => [$oldEnglishGenre->getKey()],
        ], [$customGenre->getKey()]);

        $changed = $sync->syncEditableTagBuckets(
            $product,
            Genre::LANGUAGE_ENGLISH,
            [$newEnglishGenre->getKey()],
            null,
        );

        $this->assertTrue($changed);
        $product->refresh()->load(['japaneseGenres', 'englishGenres', 'customGenres']);
        $this->assertSame(['Japanese Only'], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['New English'], $product->englishGenres->pluck('title')->all());
        $this->assertSame(['Custom Preserved'], $product->customGenres->pluck('title')->all());
        $this->assertDatabaseMissing('genre_product', [
            'product_id' => $product->getKey(),
            'genre_id' => $oldEnglishGenre->getKey(),
        ]);
    }

    public function test_editable_japanese_sync_replaces_japanese_and_preserves_english_rows(): void
    {
        $product = Product::factory()->create();
        $oldJapaneseGenre = Genre::query()->create(['title' => 'Old Japanese']);
        $newJapaneseGenre = Genre::query()->create(['title' => 'New Japanese']);
        $englishGenre = Genre::query()->create(['title' => 'English Preserved']);
        $sync = app(ProductGenreSync::class);

        $sync->sync($product, [
            Genre::LANGUAGE_JAPANESE => [$oldJapaneseGenre->getKey()],
            Genre::LANGUAGE_ENGLISH => [$englishGenre->getKey()],
        ], []);

        $changed = $sync->syncEditableTagBuckets(
            $product,
            Genre::LANGUAGE_JAPANESE,
            [$newJapaneseGenre->getKey()],
            null,
        );

        $this->assertTrue($changed);
        $product->refresh()->load(['japaneseGenres', 'englishGenres']);
        $this->assertSame(['New Japanese'], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['English Preserved'], $product->englishGenres->pluck('title')->all());
        $this->assertDatabaseMissing('genre_product', [
            'product_id' => $product->getKey(),
            'genre_id' => $oldJapaneseGenre->getKey(),
        ]);
    }

    public function test_editable_sync_keeps_fetched_over_custom_precedence_across_languages(): void
    {
        $product = Product::factory()->create();
        $sharedGenre = Genre::query()->create(['title' => 'Shared']);
        $customGenre = Genre::query()->create(['title' => 'Custom']);
        $sync = app(ProductGenreSync::class);

        $sync->sync($product, [
            Genre::LANGUAGE_JAPANESE => [$sharedGenre->getKey()],
        ], []);

        $changed = $sync->syncEditableTagBuckets(
            $product,
            Genre::LANGUAGE_ENGLISH,
            [$sharedGenre->getKey()],
            [$sharedGenre->getKey(), $customGenre->getKey()],
        );

        $this->assertTrue($changed);
        $product->refresh()->load(['englishGenres', 'japaneseGenres', 'customGenres']);
        $this->assertSame(['Shared'], $product->englishGenres->pluck('title')->all());
        $this->assertSame(['Shared'], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['Custom'], $product->customGenres->pluck('title')->all());

        $pivots = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('genre_id', $sharedGenre->getKey())
            ->get();

        $this->assertCount(1, $pivots);
        $this->assertSame(Genre::PIVOT_SOURCE_FETCHED, $pivots[0]->source);
        $this->assertEqualsCanonicalizing([
            Genre::LANGUAGE_ENGLISH,
            Genre::LANGUAGE_JAPANESE,
        ], DB::table('genre_product_languages')
            ->where('genre_product_id', $pivots[0]->id)
            ->pluck('language')
            ->all());

        $this->assertFalse($sync->syncEditableTagBuckets(
            $product,
            Genre::LANGUAGE_ENGLISH,
            [$sharedGenre->getKey()],
            [$sharedGenre->getKey(), $customGenre->getKey()],
        ));
    }

    public function test_sync_adds_every_missing_parent_and_ancestor_as_custom(): void
    {
        $grandparent = Genre::query()->create(['title' => 'Grandparent Tag']);
        $parent = Genre::query()->create(['title' => 'Parent Tag']);
        $secondParent = Genre::query()->create(['title' => 'Second Parent Tag']);
        $child = Genre::query()->create(['title' => 'Child Tag']);
        $parent->parents()->attach($grandparent);
        $child->parents()->attach([$parent->getKey(), $secondParent->getKey()]);
        $product = Product::factory()->create();

        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_ENGLISH => [$child->getKey()],
        ], []);

        $sources = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->pluck('source', 'genre_id');

        $this->assertSame(Genre::PIVOT_SOURCE_FETCHED, $sources[$child->getKey()]);
        $this->assertSame(Genre::PIVOT_SOURCE_CUSTOM, $sources[$parent->getKey()]);
        $this->assertSame(Genre::PIVOT_SOURCE_CUSTOM, $sources[$secondParent->getKey()]);
        $this->assertSame(Genre::PIVOT_SOURCE_CUSTOM, $sources[$grandparent->getKey()]);
    }

    public function test_custom_sync_adds_the_child_and_all_ancestors_as_custom(): void
    {
        $grandparent = Genre::query()->create(['title' => 'Custom Grandparent']);
        $parent = Genre::query()->create(['title' => 'Custom Parent']);
        $child = Genre::query()->create(['title' => 'Custom Child']);
        $parent->parents()->attach($grandparent);
        $child->parents()->attach($parent);
        $product = Product::factory()->create();

        app(ProductGenreSync::class)->syncCustom($product, [$child->getKey()]);

        $this->assertEqualsCanonicalizing(
            [$grandparent->getKey(), $parent->getKey(), $child->getKey()],
            $product->customGenres()->pluck('genres.id')->all(),
        );
    }

    public function test_parent_that_is_already_fetched_remains_fetched(): void
    {
        $parent = Genre::query()->create(['title' => 'Already Fetched Parent']);
        $child = Genre::query()->create(['title' => 'Fetched Child']);
        $child->parents()->attach($parent);
        $product = Product::factory()->create();

        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_ENGLISH => [$parent->getKey()],
            Genre::LANGUAGE_JAPANESE => [$child->getKey()],
        ], []);

        $parentPivot = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('genre_id', $parent->getKey())
            ->first();

        $this->assertSame(Genre::PIVOT_SOURCE_FETCHED, $parentPivot->source);
        $this->assertSame(
            [Genre::LANGUAGE_ENGLISH],
            DB::table('genre_product_languages')
                ->where('genre_product_id', $parentPivot->id)
                ->pluck('language')
                ->all(),
        );
    }
}
