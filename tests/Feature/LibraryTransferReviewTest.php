<?php

namespace Tests\Feature;

use App\Enums\LibraryImportDecision;
use App\Enums\LibraryImportItemStatus;
use App\Enums\LibraryTransferAction;
use App\Enums\LibraryTransferPartStatus;
use App\Enums\LibraryTransferRunStatus;
use App\Livewire\OptionsTransferRun;
use App\Models\Genre;
use App\Models\GenreGroup;
use App\Models\LibraryImportItem;
use App\Models\LibraryTransferRun;
use App\Models\Option;
use App\Models\Product;
use App\Support\LibraryMutationLock;
use App\Support\ProductContributorSync;
use App\Support\ProductImagePromotion;
use App\Support\Transfers\ImportReview;
use App\Support\Transfers\ImportValue;
use App\Support\Transfers\LibraryData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\Feature\Concerns\InteractsWithLibraryTransfers;
use Tests\TestCase;
use ZipArchive;

class LibraryTransferReviewTest extends TestCase
{
    use InteractsWithLibraryTransfers;

    public function test_review_values_and_natural_fields_are_not_duplicated_in_json(): void
    {
        $run = LibraryTransferRun::create(['direction' => 'import', 'status' => 'review', 'settings' => []]);
        $item = $this->review()->item(
            $run,
            'tag-library',
            'groups',
            'group-key',
            ['title' => 'Current'],
            ['title' => 'Imported'],
            ['collection' => true, 'record_identity' => str_repeat('a', 64), 'archive_order' => 2],
        )->fresh();

        $this->assertSame(['title' => 'Current'], $item->baseline);
        $this->assertSame(['title' => 'Imported'], $item->incoming);
        $this->assertTrue($item->collection);
        $this->assertSame(str_repeat('a', 64), $item->record_identity);
        $this->assertSame(['archive_order' => 2], $item->metadata);
        $this->assertFalse(Schema::hasColumn('library_transfer_entries', 'metadata'));
    }

    public function test_group_membership_review_uses_the_clear_ui_label(): void
    {
        $this->assertSame('Tags in Groups', ImportValue::label('memberships'));
        $this->assertSame('Parent Tags', ImportValue::label('relationships'));
    }

    public function test_stale_category_blocks_the_selected_work_batch_and_refreshes_its_choices(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456', 'work_name' => 'Archive title', 'notes' => 'Archive notes']);
        $export = $this->exported();
        $product->update(['work_name' => 'Local title', 'notes' => null]);
        $run = $this->imported($export);
        $product->update(['work_name' => 'Newer local title']);
        $this->apply($run);
        $this->assertSame('Newer local title', $product->fresh()->work_name);
        $this->assertNull($product->fresh()->notes);
        $conflicts = $run->items()->where('status', 'conflict')->count();
        $this->assertGreaterThan(1, $conflicts);
        $this->service()->action($run->fresh(), LibraryTransferAction::Refresh);
        $this->assertSame($conflicts, $run->items()->where('status', 'pending')->where('decision', 'ignore')->count());
    }

    public function test_data_and_images_are_separate_and_missing_image_parts_can_be_permanently_skipped(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $image = UploadedFile::fake()->image('cover.png');
        Storage::disk('public')->put('Works/RJ123456/cover.png', file_get_contents($image->getRealPath()));
        $export = $this->exported(['works', 'images']);
        $this->assertSame(['data' => 1, 'images' => 1], $export->settings['parts']);
        $run = $this->service()->startImport();
        $data = $export->parts()->where('kind', 'data')->first();
        $part = $this->service()->upload($run, new UploadedFile(Storage::disk('local')->path($data->path), $data->filename, null, null, true));
        $duplicate = $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($data->path), $data->filename, null, null, true));
        $this->assertSame($part->id, $duplicate->id);
        $this->service()->inspect($run->fresh(), $part->id);
        $this->assertSame(LibraryTransferRunStatus::WaitingForParts, $run->fresh()->status);
        $this->service()->action($run->fresh(), LibraryTransferAction::WithoutImages);
        $this->service()->analyze($run->fresh());
        $this->assertFalse($run->items()->whereIn('category', ['cover', 'sample_images'])->exists());
        $this->apply($run);
        $this->assertSame('storage/Works/RJ123456/cover.png', $product->fresh()->work_image);
    }

    public function test_complete_empty_images_can_clear_but_unavailable_categories_never_clear(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456', 'work_image' => null]);
        $export = $this->exported(['works', 'images']);
        $product->refresh()->update(['work_image' => 'storage/Works/RJ123456/missing.jpg']);
        $run = $this->imported($export);
        $this->apply($run);
        $this->assertNull($product->fresh()->work_image);
        $product->refresh()->update(['work_image' => 'storage/Works/RJ123456/missing.jpg']);
        $export = $this->exported(['works', 'images']);
        $run = $this->imported($export);
        $this->assertSame(LibraryImportItemStatus::Unavailable, $run->items()->where('category', 'cover')->first()->status);
        $this->apply($run);
        $this->assertSame('storage/Works/RJ123456/missing.jpg', $product->fresh()->work_image);
    }

    public function test_tag_library_overwrite_deletes_missing_groups_not_tags_and_propagates_parents(): void
    {
        $child = Genre::create(['title' => 'Child']);
        $parent = Genre::create(['title' => 'Parent']);
        $group = GenreGroup::create(['title' => 'Included']);
        $group->genres()->attach($child->id, ['order' => 1]);
        $child->parents()->attach($parent->id);
        $export = $this->exported(['tag-library']);
        $child->parents()->detach();
        $absent = GenreGroup::create(['title' => 'Local only']);
        $absent->genres()->attach($parent->id, ['order' => 1]);
        $product = Product::factory()->create();
        $product->genres()->attach($child->id, ['source' => Genre::PIVOT_SOURCE_CUSTOM]);
        $run = $this->imported($export);
        $this->apply($run);
        $this->assertDatabaseMissing('genre_groups', ['id' => $absent->id]);
        $this->assertDatabaseHas('genres', ['id' => $parent->id]);
        $this->assertTrue($product->genres()->whereKey($parent->id)->exists());
        $this->assertFalse($run->items()->whereIn('status', ['failed', 'conflict', 'unavailable'])->exists(), $run->items()->pluck('error')->toJson());
    }

    public function test_group_collection_stales_if_a_group_is_added_after_analysis(): void
    {
        $export = $this->exported(['tag-library']);
        $run = $this->imported($export);
        $group = GenreGroup::create(['title' => 'New local group']);
        $this->apply($run);
        $this->assertNotNull($group->fresh());
        $this->assertSame(LibraryImportItemStatus::Conflict, $run->items()->where('category', 'groups')->first()->status);
    }

    public function test_options_merge_preserves_absent_subkeys_and_overwrite_resets_absent_portable_keys(): void
    {
        $export = $this->exported(['options']);
        $this->rewriteData($export->parts()->first(), fn($records) => [['key' => Option::OPTIONAL_PRODUCT_STATUSES, 'value' => ['on_hold' => true]]]);
        Option::setOptionalProductStatuses(['on_hold' => false, 'dropped' => true]);
        Option::setIndexImageViewerEnabled(true);
        Option::setExportPartMib(8192);
        $run = $this->imported($export);
        $this->apply($run, 'merge');
        $this->assertSame(['on_hold' => true, 'dropped' => true], Option::optionalProductStatuses());
        $this->assertTrue(Option::indexImageViewerEnabled());
        $run = $this->imported($export);
        $this->apply($run, 'overwrite');
        $this->assertFalse(Option::indexImageViewerEnabled());
        $this->assertSame(8192, Option::exportPartMib());
        $this->assertSame(['on_hold' => true, 'dropped' => Option::DEFAULT_OPTIONAL_PRODUCT_STATUSES['dropped']], Option::optionalProductStatuses());
    }

    public function test_work_merge_keeps_zero_and_existing_scalars_and_fills_null(): void
    {
        $product = Product::factory()->create(['notes' => 'Incoming', 'series' => 'Incoming series', 'num_re_listen_times' => 5]);
        $export = $this->exported();
        $product->update(['notes' => 'Local', 'series' => null, 'num_re_listen_times' => 0]);
        $run = $this->imported($export);
        $this->apply($run, 'merge');
        $this->assertSame('Local', $product->fresh()->notes);
        $this->assertSame('Incoming series', $product->fresh()->series);
        $this->assertSame(0, $product->fresh()->num_re_listen_times);
    }

    public function test_exact_overwrite_restores_timestamps_but_mixed_choices_do_not(): void
    {
        $product = Product::factory()->create(['created_at' => '2020-01-01', 'updated_at' => '2020-02-02']);
        $export = $this->exported();
        $product->forceFill(['created_at' => '2024-01-01', 'notes' => 'Local'])->save();
        $run = $this->imported($export);
        $this->apply($run);
        $this->assertSame('2020-01-01', $product->fresh()->created_at->toDateString());
        $this->assertSame('2020-02-02', $product->fresh()->updated_at->toDateString());
        $product->refresh()->forceFill(['created_at' => '2024-01-01', 'notes' => 'Local'])->save();
        $run = $this->imported($export);
        $run->items()->where('status', 'pending')->update(['decision' => 'overwrite']);
        $run->items()->where('category', 'titles')->update(['decision' => 'ignore']);
        $this->service()->action($run, LibraryTransferAction::Apply);
        $this->service()->apply($run->fresh());
        $this->assertSame('2024-01-01', $product->fresh()->created_at->toDateString());
        $this->assertSame(now()->toDateString(), $product->fresh()->updated_at->toDateString());
    }

    public function test_new_work_tag_dependencies_do_not_make_its_tag_metadata_stale(): void
    {
        $tag = Genre::create(['title' => 'Archive tag', 'description' => 'Metadata']);
        $product = Product::factory()->create();
        $product->genres()->attach($tag->id, ['source' => Genre::PIVOT_SOURCE_CUSTOM]);
        $export = $this->exported(['works', 'tag-library']);
        $product->delete();
        $tag->delete();
        $run = $this->imported($export);
        $this->apply($run);
        $this->assertDatabaseHas('genres', ['title' => 'Archive tag', 'description' => 'Metadata']);
        $this->assertSame(0, $run->items()->whereIn('status', ['conflict', 'failed'])->count(), $run->items()->pluck('error')->toJson());
    }

    public function test_group_merge_appends_in_archive_order_and_preserves_existing_memberships(): void
    {
        $tag = Genre::create(['title' => 'Archive']);
        $one = GenreGroup::create(['title' => 'One']);
        $two = GenreGroup::create(['title' => 'Two']);
        $one->genres()->attach($tag->id, ['order' => 1]);
        $export = $this->exported(['tag-library']);
        $one->delete();
        $two->delete();
        GenreGroup::create(['title' => 'Local first']);
        $run = $this->imported($export);
        $this->apply($run, 'merge');
        $this->assertSame(['Local first', 'One', 'Two'], GenreGroup::query()->ordered()->pluck('title')->all());
        $this->assertSame(['Archive'], GenreGroup::where('title', 'One')->first()->genres->pluck('title')->all());
    }

    public function test_cycle_in_resulting_graph_is_rejected_atomically(): void
    {
        $a = Genre::create(['title' => 'A']);
        $b = Genre::create(['title' => 'B']);
        $b->parents()->attach($a->id);
        $export = $this->exported(['tag-library']);
        $b->parents()->detach();
        $a->parents()->attach($b->id);
        $run = $this->imported($export);
        $this->apply($run, 'merge');
        $this->assertSame(LibraryImportItemStatus::Failed, $run->items()->where('category', 'relationships')->first()->status);
        $this->assertSame([$b->id], $a->parents()->pluck('genres.id')->all());
        $this->assertSame([], $b->parents()->pluck('genres.id')->all());
    }

    public function test_image_file_edit_after_analysis_is_stale_even_when_database_paths_match(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);
        $run = $this->imported($export);
        $hash = $this->saveImage('Works/RJ123456/cover.png', 20);
        $this->apply($run);
        $this->assertSame(LibraryImportItemStatus::Conflict, $run->items()->where('category', 'cover')->first()->status);
        $this->assertSame($hash, hash_file('sha256', Storage::disk('public')->path('Works/RJ123456/cover.png')));
    }

    public function test_new_work_failure_after_image_promotion_rolls_back_database_and_files(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $hash = $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);
        $product->delete();
        $run = $this->imported($export);
        $thrown = false;
        LibraryImportItem::updating(function ($item) use (&$thrown) {
            if (! $thrown && $item->status === LibraryImportItemStatus::Applied) {
                $thrown = true;
                throw new RuntimeException('Simulated result write failure');
            }
        });
        try {
            $this->apply($run);
            $this->fail('Operational write failures must remain retryable.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated result write failure', $exception->getMessage());
        } finally {
            LibraryImportItem::flushEventListeners();
        }
        $this->assertDatabaseMissing('products', ['id' => 'RJ123456']);
        $this->assertSame($hash, hash_file('sha256', Storage::disk('public')->path('Works/RJ123456/cover.png')));
        $this->assertSame(LibraryImportItemStatus::Pending, $run->items()->first()->status);
    }

    public function test_merge_samples_deduplicates_hashes_and_overwrite_preserves_order(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456', 'work_image' => null, 'sample_images' => ['storage/Works/RJ123456/sample_1.png', 'storage/Works/RJ123456/sample_2.png']]);
        $one = $this->saveImage('Works/RJ123456/sample_1.png');
        $two = $this->saveImage('Works/RJ123456/sample_2.png', 20);
        $export = $this->exported(['works', 'images']);
        $product->update(['sample_images' => ['storage/Works/RJ123456/sample_2.png']]);
        $run = $this->imported($export);
        $this->apply($run, 'merge');
        $files = app(LibraryData::class)->imageCategory($product->fresh(), 'sample_images')['files'];
        $this->assertSame([$two, $one], array_column($files, 'sha256'));
        $run = $this->imported($export);
        $this->apply($run);
        $files = app(LibraryData::class)->imageCategory($product->fresh(), 'sample_images')['files'];
        $this->assertSame([$one, $two], array_column($files, 'sha256'));
    }

    public function test_corrupt_image_part_allows_only_explicit_data_only_continuation(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);
        $image = $export->parts()->where('kind', 'images')->first();
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($image->path));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['entries'][0]['sha256'] = str_repeat('0', 64);
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();
        $run = $this->service()->startImport();
        foreach ($export->parts()->get() as $part) {
            $upload = $this->service()->upload($run->fresh(), new UploadedFile(Storage::disk('local')->path($part->path), $part->filename, null, null, true));
            $this->service()->inspect($run->fresh(), $upload->id);
        }
        $this->assertSame(LibraryTransferRunStatus::WaitingForParts, $run->fresh()->status);
        $this->assertSame(LibraryTransferPartStatus::Invalid, $run->parts()->where('kind', 'images')->first()->status);
        $this->service()->action($run->fresh(), LibraryTransferAction::WithoutImages);
        $this->service()->analyze($run->fresh());
        $this->assertSame(LibraryTransferRunStatus::Review, $run->fresh()->status);
        $this->assertFalse($run->items()->whereIn('category', ['cover', 'sample_images'])->exists());
    }

    public function test_large_reviews_are_bounded_but_complete_change_json_remains_available(): void
    {
        $description = str_repeat('Detailed text. ', 3000);
        Product::factory()->create(['description' => $description]);
        $run = $this->imported($this->exported());
        Livewire::test(OptionsTransferRun::class, ['run' => $run])->call('tab', 'works', 'descriptions')
            ->assertSee('Preview truncated.')->assertDontSee($description);
        $item = $run->items()->where('metadata->field', 'description')->first();
        $response = $this->get(route('options.transfers.change', [$run, $item]))->assertOk();
        $this->assertSame($description, $response->json('incoming'));
        $other = $this->service()->startImport();
        $this->get(route('options.transfers.change', [$other, $item]))->assertNotFound();
    }

    public function test_tab_defaults_preserve_explicit_choices_and_refresh_forces_ignore(): void
    {
        Product::factory()->create(['id' => 'RJ123456', 'work_name' => 'Before']);
        $run = $this->imported($this->exported());
        $title = $run->items()->where('metadata->field', 'work_name')->first();
        $english = $run->items()->where('metadata->field', 'work_name_english')->first();
        $view = Livewire::test(OptionsTransferRun::class, ['run' => $run])->call('tab', 'works', 'titles')
            ->call('decide', $title->id, 'ignore')->call('decideMany', 'overwrite', true)->assertHasNoErrors();
        $this->assertSame(LibraryImportDecision::Ignore, $title->fresh()->decision);
        $this->assertTrue($title->fresh()->decision_override);
        $this->assertSame(LibraryImportDecision::Overwrite, $english->fresh()->decision);
        $view->call('decide', $title->id, 'inherit');
        $this->assertSame(LibraryImportDecision::Overwrite, $title->fresh()->decision);
        $title->update(['status' => 'conflict']);
        $this->service()->action($run->fresh(), LibraryTransferAction::Refresh);
        $this->assertSame(LibraryImportDecision::Ignore, $title->fresh()->decision);
        $this->assertTrue($title->fresh()->decision_override);
        $view->call('decideMany', 'merge');
        $this->assertSame(LibraryImportDecision::Ignore, $title->fresh()->decision);
    }

    public function test_work_review_uses_refetch_tabs_and_separate_dlsite_list_fields(): void
    {
        Product::factory()->create(['id' => 'RJ123456']);
        $this->saveImage('Works/RJ123456/cover.jpg');
        $run = $this->imported($this->exported(['works', 'images']));
        $expectedCategories = [
            'titles',
            'descriptions',
            'series',
            'age',
            'circle',
            'maker',
            'scenario',
            'voice_actor',
            'illustration',
            'author',
            'tags',
            'cover',
            'sample_images',
            'notes',
            'score',
            'progress',
            'start_date',
            'end_date',
            'num_re_listen_times',
            're_listen_value',
            'priority',
            'custom_tags',
        ];
        $actualCategories = $run->items()->where('section', 'works')->distinct()->pluck('category')->all();

        $this->assertEqualsCanonicalizing($expectedCategories, $actualCategories);
        $this->get(route('options.transfers.show', $run))
            ->assertOk()
            ->assertSeeInOrder(array_map(
                fn(string $category): string => 'id="transfer-category-tab-works-' . $category . '"',
                $expectedCategories,
            ), false);

        $component = Livewire::test(OptionsTransferRun::class, ['run' => $run]);
        foreach (['num_re_listen_times', 're_listen_value'] as $category) {
            $component->call('tab', 'works', $category)
                ->assertSet('section', 'works')
                ->assertSet('category', $category)
                ->assertViewHas('items', fn($items): bool => $items->isNotEmpty()
                    && $items->every(fn($item): bool => $item->section === 'works' && $item->category === $category));
        }
    }

    public function test_applying_one_contributor_tab_preserves_other_roles(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456']);
        $contributors = app(ProductContributorSync::class);
        $contributors->sync($product, [
            'scenario' => ['Archive Scenario'],
            'voice_actor' => ['Archive Voice'],
        ]);
        $export = $this->exported();
        $contributors->sync($product, [
            'scenario' => ['Local Scenario'],
            'voice_actor' => ['Local Voice'],
        ]);
        $run = $this->imported($export);
        $run->items()->where('category', 'scenario')->update(['decision' => 'overwrite']);

        $this->service()->action($run, LibraryTransferAction::Apply, 'works', 'scenario');
        $this->service()->apply($run->fresh());
        $saved = $contributors->namesByRole($product->fresh());

        $this->assertSame(['Archive Scenario'], $saved['scenario']);
        $this->assertSame(['Local Voice'], $saved['voice_actor']);
    }

    public function test_partial_contributor_document_preserves_omitted_roles_and_can_clear_explicit_roles(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456']);
        $contributors = app(ProductContributorSync::class);
        $original = [
            'circle' => ['Local Circle'],
            'scenario' => ['Local Scenario'],
            'voice_actor' => ['Local Voice'],
            'illustration' => ['Local Illustrator'],
            'author' => ['Local Author'],
        ];
        $contributors->sync($product, $original);
        foreach ([['Incoming Voice'], []] as $voices) {
            $export = $this->exported();
            $this->rewriteWorkData($export->parts()->first(), fn() => [
                'japanese' => ['product_id' => $product->id, 'work_name' => $product->work_name],
                'english' => ['voice_actor' => $voices],
            ]);
            $run = $this->imported($export);
            $this->assertSame(['voice_actor'], $run->items()->whereIn('category', array_keys($original))->pluck('category')->all());
            $this->apply($run);
            $saved = $contributors->namesByRole($product->fresh());
            foreach (array_replace($original, ['voice_actor' => $voices]) as $role => $names) {
                $this->assertSame($names, $saved[$role] ?? []);
            }
        }
    }

    public function test_relationship_choices_are_individual_but_cycle_failure_is_atomic(): void
    {
        $a = Genre::resolveByTitle('A');
        $b = Genre::resolveByTitle('B');
        $c = Genre::resolveByTitle('C');
        $run = $this->service()->startImport();
        $run->update(['status' => 'review']);
        $this->review()->stageRelationships($run, ['a', 'b', 'c'], [['a', 'b'], ['b', 'a'], ['a', 'c']]);
        $this->assertSame(3, $run->items()->where('category', 'relationships')->count());
        $this->apply($run);
        $this->assertSame(3, $run->items()->where('status', 'failed')->count());
        $this->assertSame(0, DB::table('genre_relations')->count());
        $this->service()->action($run->fresh(), LibraryTransferAction::Refresh);
        $selected = $run->items()->where('entity_key', 'a → b')->first();
        $selected->update(['decision' => 'overwrite', 'decision_override' => true]);
        $this->service()->action($run->fresh(), LibraryTransferAction::Apply, 'tag-library', 'relationships');
        $this->service()->apply($run->fresh());
        $this->assertDatabaseHas('genre_relations', ['parent_genre_id' => $a->id, 'child_genre_id' => $b->id]);
        $this->assertDatabaseMissing('genre_relations', ['parent_genre_id' => $a->id, 'child_genre_id' => $c->id]);
    }

    public function test_relationship_scope_conflict_refresh_adds_new_removal_choices(): void
    {
        $a = Genre::resolveByTitle('A');
        $b = Genre::resolveByTitle('B');
        $c = Genre::resolveByTitle('C');
        $run = $this->service()->startImport();
        $run->update(['status' => 'review']);
        $this->review()->stageRelationships($run, ['a', 'b'], [['a', 'b']]);
        $b->children()->attach($c->id);
        $this->apply($run);
        $this->assertSame(LibraryImportItemStatus::Conflict, $run->items()->first()->status);
        $this->assertDatabaseMissing('genre_relations', ['parent_genre_id' => $a->id, 'child_genre_id' => $b->id]);
        $this->service()->action($run->fresh(), LibraryTransferAction::Refresh);
        $this->assertSame(2, $run->items()->where('category', 'relationships')->count());
        $this->assertFalse($run->items()->where('decision', '<>', 'ignore')->exists());
        $this->assertFalse($run->items()->where('entity_key', 'b → c')->first()->incoming);
    }

    public function test_real_group_title_cannot_collide_with_internal_collection_ordering(): void
    {
        GenreGroup::create(['title' => 'Group order and missing groups', 'description' => 'Imported']);
        $export = $this->exported(['tag-library']);
        GenreGroup::first()->update(['description' => 'Local']);
        $run = $this->imported($export);
        $this->assertSame(2, $run->items()->where('category', 'groups')->count());
        $this->apply($run);
        $this->assertSame(0, $run->items()->where('status', 'conflict')->count());
        $this->assertSame('Imported', GenreGroup::first()->description);
    }

    public function test_later_options_apply_never_restores_already_finalized_work_timestamps(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456', 'created_at' => '2020-01-01', 'updated_at' => '2021-01-01']);
        $run = $this->imported($this->exported(['works', 'options']));
        $run->items()->where('section', 'works')->update(['decision' => 'overwrite']);
        $this->service()->action($run->fresh(), LibraryTransferAction::Apply, 'works');
        $this->service()->apply($run->fresh());
        $this->assertSame('2021-01-01', $product->fresh()->updated_at->toDateString());
        $this->travel(1)->hours();
        $product->refresh()->update(['notes' => 'New local edit']);
        $localTime = $product->fresh()->updated_at;
        $run->items()->where('section', 'options')->update(['decision' => 'overwrite']);
        $this->service()->action($run->fresh(), LibraryTransferAction::Apply, 'options');
        $this->service()->apply($run->fresh());
        $this->assertTrue($localTime->equalTo($product->fresh()->updated_at));
        $this->travelBack();
    }

    public function test_image_recovery_restores_uncommitted_replacement_and_keeps_committed_replacement(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $run = $this->service()->startImport();
        $item = $this->review()->item($run, 'works', 'cover', $product->id, null, null);
        foreach ([false, true] as $committed) {
            $token = bin2hex(random_bytes(16));
            $destination = 'Works/RJ123456/cover.png';
            $backup = 'ImagePromotions/' . $token . '/' . $destination;
            Storage::disk('local')->put($backup, 'old image');
            Storage::disk('public')->put($destination, 'new image');
            Storage::disk('local')->put('ImagePromotions/active.json', json_encode([
                'token' => $token,
                'owner' => 'import',
                'id' => $item->id,
                'entries' => [['destination' => $destination, 'backup' => $backup]],
                'products' => [$product->id],
            ], JSON_THROW_ON_ERROR));
            $item->update(['result' => $committed ? ['_image_promotion' => $token] : []]);
            app(LibraryMutationLock::class)->run(fn() => app(ProductImagePromotion::class)->recover());
            $this->assertSame($committed ? 'new image' : 'old image', Storage::disk('public')->get($destination));
            $this->assertFalse(Storage::disk('local')->exists('ImagePromotions/active.json'));
        }
    }

    public function test_failed_image_recovery_blocks_mutation_and_retains_journal(): void
    {
        $token = bin2hex(random_bytes(16));
        $destination = 'Works/RJ123456/cover.png';
        Storage::disk('local')->put('ImagePromotions/active.json', json_encode([
            'token' => $token,
            'owner' => 'import',
            'id' => 0,
            'products' => ['RJ123456'],
            'entries' => [['destination' => $destination, 'backup' => 'ImagePromotions/' . $token . '/' . $destination]],
        ], JSON_THROW_ON_ERROR));
        $mutated = false;
        try {
            app(LibraryMutationLock::class)->run(function () use (&$mutated): void {
                app(ProductImagePromotion::class)->recover();
                $mutated = true;
            });
            $this->fail('Recovery must block unsafe mutation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('image', $exception->getMessage());
        }
        $this->assertFalse($mutated);
        $this->assertTrue(Storage::disk('local')->exists('ImagePromotions/active.json'));
    }

    public function test_data_only_continuation_skips_even_invalid_work_image_references(): void
    {
        $product = Product::factory()->create(['id' => 'RJ123456', 'work_image' => 'storage/Works/RJ123456/cover.png']);
        $hash = $this->saveImage('Works/RJ123456/cover.png');
        $export = $this->exported(['works', 'images']);
        $data = $export->parts()->where('kind', 'data')->firstOrFail();
        $this->rewriteWorkData($data, function ($work) {
            $work['dlsite_list']['cover'] = 'invalid image inventory';
            $work['dlsite_list']['sample_images'] = ['files' => [['path' => ['invalid path']]]];

            return $work;
        });
        $run = $this->service()->startImport();
        $part = $this->service()->upload($run, new UploadedFile(Storage::disk('local')->path($data->path), $data->filename, null, null, true));
        $this->service()->inspect($run->fresh(), $part->id);
        $this->service()->action($run->fresh(), LibraryTransferAction::WithoutImages);
        $this->service()->analyze($run->fresh());
        $this->assertSame(LibraryTransferRunStatus::Review, $run->fresh()->status);
        $this->assertFalse($run->items()->where('status', 'unavailable')->exists());
        $this->apply($run);
        $this->assertSame($hash, hash_file('sha256', Storage::disk('public')->path('Works/RJ123456/cover.png')));
    }

    public function test_apply_tab_does_not_apply_or_ignore_relationships_from_another_tab(): void
    {
        Product::factory()->create(['id' => 'RJ123456']);
        $a = Genre::resolveByTitle('A');
        $b = Genre::resolveByTitle('B');
        $run = $this->imported($this->exported());
        $this->review()->stageRelationships($run, ['a', 'b'], [['a', 'b']]);
        $edge = $run->items()->where('category', 'relationships')->firstOrFail();
        $edge->update(['decision' => 'overwrite']);
        $run->items()->where('category', 'titles')->update(['decision' => 'overwrite']);
        $this->service()->action($run->fresh(), LibraryTransferAction::Apply, 'works', 'titles');
        while ($run->fresh()->status === LibraryTransferRunStatus::Applying) {
            $this->service()->apply($run->fresh());
        }
        $this->assertSame(LibraryImportItemStatus::Pending, $edge->fresh()->status);
        $this->assertDatabaseMissing('genre_relations', ['parent_genre_id' => $a->id, 'child_genre_id' => $b->id]);
        $this->service()->action($run->fresh(), LibraryTransferAction::Apply, 'tag-library', 'relationships');
        $this->service()->apply($run->fresh());
        $this->assertSame(LibraryImportItemStatus::Applied, $edge->fresh()->status);
        $this->assertDatabaseHas('genre_relations', ['parent_genre_id' => $a->id, 'child_genre_id' => $b->id]);
    }

    public function test_malformed_option_layout_is_quarantined_instead_of_crashing_analysis(): void
    {
        $export = $this->exported(['options']);
        $this->rewriteData($export->parts()->firstOrFail(), fn($records) => [
            ['key' => Option::INDEX_FIELD_LAYOUT, 'value' => [['field' => ['invalid']]]],
            ['key' => Option::UI_LANGUAGE, 'value' => 'en'],
        ]);
        $run = $this->imported($export);
        $this->assertSame(LibraryTransferRunStatus::Review, $run->status);
        $this->assertSame(LibraryImportItemStatus::Unavailable, $run->items()->where('entity_key', Option::INDEX_FIELD_LAYOUT)->firstOrFail()->status);
        $this->assertSame(LibraryImportItemStatus::Pending, $run->items()->where('entity_key', Option::UI_LANGUAGE)->firstOrFail()->status);
    }

    public function test_completed_import_review_is_visible_but_permanently_read_only(): void
    {
        $run = LibraryTransferRun::create([
            'direction' => 'import',
            'status' => 'completed',
            'settings' => [],
        ]);
        $item = $this->review()->item($run, 'options', 'options', Option::INDEX_PER_PAGE, 100, 50);
        $item->update(['status' => 'applied', 'decision' => 'overwrite']);

        Livewire::test(OptionsTransferRun::class, ['run' => $run])
            ->assertSee('Review changes')
            ->assertSee('Current')
            ->assertSee('Imported')
            ->assertDontSee('Tab choice')
            ->assertDontSee('Apply Tab')
            ->assertDontSee('Apply All')
            ->assertDontSee('Refresh conflicts')
            ->call('decide', $item->id, 'ignore')
            ->assertHasErrors('transfer');

        $this->assertSame(LibraryImportDecision::Overwrite, $item->fresh()->decision);
        $this->expectExceptionMessage('unavailable in the current transfer state');
        $this->service()->action($run->fresh(), LibraryTransferAction::Apply);
    }

    public function test_parent_relationship_identity_does_not_depend_on_ambiguous_display_label(): void
    {
        foreach (['A → B', 'C', 'A', 'B → C'] as $title) {
            Genre::resolveByTitle($title);
        }
        $run = LibraryTransferRun::create(['direction' => 'import', 'status' => 'review', 'settings' => []]);

        $this->review()->stageRelationships($run, ['a → b', 'c', 'a', 'b → c'], [
            ['a → b', 'c'],
            ['a', 'b → c'],
        ]);

        $items = $run->items()->where('category', 'relationships')->get();
        $this->assertCount(2, $items);
        $this->assertSame(1, $items->pluck('entity_key')->unique()->count());
        $this->assertSame(2, $items->pluck('identity_hash')->unique()->count());
    }
}
