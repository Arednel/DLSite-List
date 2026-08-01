<?php

namespace Tests\Feature;

use App\Enums\ProductContributorRole;
use App\Enums\RefetchCategory;
use App\Jobs\FetchProductWorkJob;
use App\Livewire\OptionsRefetchReview;
use App\Models\Genre;
use App\Models\Option;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\ProductContributorSync;
use App\Support\ProductGenreSync;
use App\Support\Refetch\RefetchService;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class FullRefetchTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_creates_a_full_refetch_batch_for_custom_and_dlsite_works(): void
    {
        Bus::fake();
        $custom = Product::factory()->create([
            'id' => 'RJ000000002',
            'maker_id' => null,
        ]);
        $dlsite = Product::factory()->create([
            'id' => 'RJ000000010',
        ]);

        $response = $this->post(route('options.refetch.start'), [
            'scope' => 'all',
            'check_images' => '1',
        ]);

        $run = RefetchRun::query()->firstOrFail();

        $response->assertRedirect(route('options.refetch.show', $run));
        $this->assertTrue($run->check_images);
        $this->assertSame(
            [$dlsite->id, $custom->id],
            $run->results()->orderBy('id')->pluck('product_id')->all(),
        );

        Bus::assertBatched(function (PendingBatch $batch) use ($run, $dlsite, $custom): bool {
            return $batch->name === "Refetch works #{$run->id}"
                && $batch->jobs->map(
                    fn(FetchProductWorkJob $job): string => $job->productId
                )->all() === [$dlsite->id, $custom->id];
        });
    }

    public function test_selected_start_validates_selection_and_queues_only_resolved_works(): void
    {
        Bus::fake();
        $first = Product::factory()->create(['id' => 'RJ000000002']);
        $second = Product::factory()->create(['id' => 'RJ000000010']);

        $this->from(route('options.index', ['tab' => 'refetch']))
            ->post(route('options.refetch.start'), [
                'scope' => 'selected',
                'product_ids' => [],
            ])
            ->assertRedirect(route('options.index', ['tab' => 'refetch']))
            ->assertSessionHasErrors('product_ids');

        $this->post(route('options.refetch.start'), [
            'scope' => 'selected',
            'product_ids' => [$first->id],
        ])->assertRedirect();

        $run = RefetchRun::query()->firstOrFail();
        $this->assertFalse($run->check_images);
        $this->assertSame([$first->id], $run->results()->pluck('product_id')->all());
        $this->assertDatabaseMissing('refetch_work_results', [
            'refetch_run_id' => $run->id,
            'product_id' => $second->id,
        ]);
        Bus::assertBatched(fn(PendingBatch $batch): bool => $batch->jobs->count() === 1
            && $batch->jobs->first()->productId === $first->id);
    }

    public function test_starting_all_requires_an_existing_work(): void
    {
        Bus::fake();

        $this->post(route('options.refetch.start'), ['scope' => 'all'])
            ->assertRedirect(route('options.index', ['tab' => 'refetch']))
            ->assertSessionHasErrors('product_ids');

        $this->assertDatabaseCount('refetch_runs', 0);
        Bus::assertNothingBatched();
    }

    public function test_review_renders_role_specific_tabs_and_ignore_defaults(): void
    {
        [$run,, $result] = $this->reviewRun();

        $this->assertArrayHasKey(RefetchCategory::Titles->value, $result->fresh()->changes);
        $this->assertNotEmpty($result->fresh()->changesFor(RefetchCategory::Titles));

        $response = $this->get(route('options.refetch.show', $run));

        $response->assertOk()
            ->assertSeeInOrder([
                'Titles',
                'Descriptions',
                'Series',
                'Age',
                'Circle',
                'Maker ID',
                'Scenario Author',
                'Voice Actor',
                'Illustration Author',
                'Author',
                'Tags',
                'Cover',
                'Sample Images',
            ])
            ->assertSee('wire:model="globalActions.titles"', false)
            ->assertSee('Set Overwrite for All')
            ->assertSee('Sets each unresolved tab that contains changes to Overwrite.')
            ->assertSee('title-tooltips.css', false)
            ->assertSee('Apply All Tabs')
            ->assertSee('Reject Run');
    }

    public function test_fetch_records_full_metadata_for_a_custom_created_work_without_overwriting_json(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $product = Product::factory()->create([
            'maker_id' => null,
            'work_name' => 'Custom Title',
        ]);
        $run = app(RefetchService::class)->createRun([$product->id], false);
        $result = $run->results()->firstOrFail();
        $stagedPath = "Refetch/{$run->id}/Works/{$product->id}.json";
        Storage::disk('local')->put($stagedPath, json_encode([
            'japanese' => [
                'product_id' => $product->id,
                'maker_id' => 'RGNEW',
                'work_name' => 'DLSite Title',
                'age_category' => ['_name_' => 'R18'],
                'circle' => 'DLSite Circle',
            ],
            'english' => [],
        ], JSON_THROW_ON_ERROR));
        Process::fake([
            '*' => Process::result(output: json_encode([
                'product_id' => $product->id,
                'downloaded_images' => [],
                'failed_images' => [],
            ], JSON_THROW_ON_ERROR)),
        ])->preventStrayProcesses();

        app(RefetchService::class)->fetchAndRecordResult($result);

        $this->assertTrue($result->refresh()->isFetched());
        $this->assertArrayHasKey(RefetchCategory::Titles->value, $result->changes);
        $this->assertArrayHasKey(RefetchCategory::Maker->value, $result->changes);
        $this->assertSame(RefetchRun::STATUS_REVIEW, $run->refresh()->status);
        $this->assertFalse(
            Storage::disk('local')->exists("Works/{$product->id}.json")
        );
    }

    public function test_image_failures_keep_metadata_reviewable_and_disable_only_failed_image_categories(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $product = Product::factory()->create(['work_name' => 'Old Title']);
        $run = app(RefetchService::class)->createRun([$product->id], true);
        $result = $run->results()->firstOrFail();
        $stagedPath = "Refetch/{$run->id}/Works/{$product->id}.json";
        Storage::disk('local')->put($stagedPath, json_encode([
            'japanese' => [
                'product_id' => $product->id,
                'work_name' => 'New Title',
                'work_image' => 'cover-url',
                'sample_images' => ['sample-url'],
            ],
            'english' => [],
        ], JSON_THROW_ON_ERROR));
        Process::fake([
            '*' => Process::result(output: json_encode([
                'product_id' => $product->id,
                'downloaded_images' => [],
                'failed_images' => ['cover.jpg', 'sample_1.jpg'],
            ], JSON_THROW_ON_ERROR)),
        ])->preventStrayProcesses();

        app(RefetchService::class)->fetchAndRecordResult($result);

        $result->refresh();
        $this->assertTrue($result->isFetched());
        $this->assertArrayHasKey('titles', $result->changes);
        $this->assertArrayNotHasKey('cover', $result->changes);
        $this->assertArrayNotHasKey('sample_images', $result->changes);
        $this->assertSame([
            'Cover image download failed after five attempts.',
            'Sample image download failed after five attempts: :images',
        ], collect($result->warnings)->pluck('key')->all());
        Process::assertRanTimes(fn(): bool => true, 5);
    }

    public function test_tabs_apply_incrementally_and_json_is_promoted_only_when_finished(): void
    {
        Storage::fake('local');
        [$run, $product, $result] = $this->reviewRun();
        Storage::disk('local')->put("Works/{$product->id}.json", '{"version":"old"}');
        Storage::disk('local')->put($this->stagedJsonPath($run, $product), '{"version":"new"}');

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set(
                'globalActions.' . RefetchCategory::Titles->value,
                RefetchService::ACTION_OVERWRITE,
            )
            ->call('applyTab', RefetchCategory::Titles->value)
            ->assertNoRedirect();

        $this->assertSame('New Title', $product->refresh()->work_name);
        $this->assertSame('Old Description', $product->description);
        $this->assertSame('{"version":"old"}', Storage::disk('local')->get("Works/{$product->id}.json"));
        $this->assertSame(RefetchRun::STATUS_REVIEW, $run->refresh()->status);

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->call('rejectOrFinish')
            ->assertRedirectToRoute('options.refetch.show', $run);

        $this->assertSame('Old Description', $product->refresh()->description);
        $this->assertSame(RefetchRun::STATUS_APPLIED, $run->refresh()->status);
        $this->assertSame('{"version":"new"}', Storage::disk('local')->get("Works/{$product->id}.json"));
    }

    public function test_failed_json_promotion_keeps_the_run_retryable(): void
    {
        [$run, $product, $result] = $this->reviewRun();
        $run->forceFill([
            'resolved_tabs' => array_values(array_unique([
                ...$run->resolved_tabs,
                RefetchCategory::Descriptions->value,
            ])),
        ])->save();
        $localDisk = Mockery::mock(Filesystem::class);
        $localDisk->shouldReceive('exists')
            ->once()
            ->with($this->stagedJsonPath($run, $product))
            ->andReturnTrue();
        $localDisk->shouldReceive('copy')
            ->once()
            ->with($this->stagedJsonPath($run, $product), "Works/{$product->id}.json")
            ->andReturnFalse();
        Storage::shouldReceive('disk')
            ->twice()
            ->with('local')
            ->andReturn($localDisk);

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set('globalActions.titles', RefetchService::ACTION_OVERWRITE)
            ->call('applyTab', RefetchCategory::Titles->value)
            ->assertHasErrors('run')
            ->assertSee('Failed to promote staged refetch file.')
            ->assertNoRedirect();

        $run->refresh();
        $this->assertSame(RefetchRun::STATUS_REVIEW, $run->status);
        $this->assertFalse($run->tabResolved(RefetchCategory::Titles));
    }

    public function test_each_change_can_override_its_tab_global_choice(): void
    {
        [$run, $product, $result] = $this->reviewRun();
        $changes = $result->changes;
        $changes['titles']['work_name_english'] = [
            'label' => 'English Title',
            'old' => $product->work_name_english,
            'new' => 'New English Title',
        ];
        $result->forceFill(['changes' => $changes])->save();

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set('globalActions.titles', 'overwrite')
            ->set("actions.titles.{$result->id}.work_name", 'ignore')
            ->set("actions.titles.{$result->id}.work_name_english", 'overwrite')
            ->call('applyTab', 'titles')
            ->assertNoRedirect();

        $this->assertSame('Old Title', $product->refresh()->work_name);
        $this->assertSame('New English Title', $product->work_name_english);
        $result->refresh();
        $this->assertSame('ignore', data_get($result->decisions, 'titles.work_name.action'));
        $this->assertSame('overwrite', data_get($result->decisions, 'titles.work_name_english.action'));
    }

    public function test_reject_before_any_tab_is_applied_keeps_product_and_json_unchanged(): void
    {
        Storage::fake('local');
        [$run, $product, $result] = $this->reviewRun();
        Storage::disk('local')->put("Works/{$product->id}.json", '{"version":"old"}');
        Storage::disk('local')->put($this->stagedJsonPath($run, $product), '{"version":"new"}');

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->call('rejectOrFinish')
            ->assertRedirectToRoute('options.refetch.show', $run);

        $this->assertSame(RefetchRun::STATUS_REJECTED, $run->refresh()->status);
        $this->assertSame('Old Title', $product->refresh()->work_name);
        $this->assertSame('{"version":"old"}', Storage::disk('local')->get("Works/{$product->id}.json"));
    }

    public function test_cancellation_settles_to_review_and_retains_failed_history(): void
    {
        $product = Product::factory()->create();
        $run = app(RefetchService::class)->createRun([$product->id], false);
        $result = $run->results()->firstOrFail();

        $this->post(route('options.refetch.cancel', $run))
            ->assertRedirect(route('options.refetch.show', $run));

        $this->assertSame(RefetchRun::STATUS_CANCELLING, $run->refresh()->status);
        $this->assertNotNull($run->cancelled_at);

        app(RefetchService::class)->recordFailedResult(
            $result,
            RefetchService::CANCELLED_BEFORE_FETCH_MESSAGE,
        );

        $run->refresh();
        $this->assertSame(RefetchRun::STATUS_REVIEW, $run->status);
        $this->assertSame(1, $run->total_count);
        $this->assertSame(1, $run->processed_count);
        $this->assertSame(0, $run->fetched_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertTrue($run->hasReviewResults());

        $this->get(route('options.refetch.show', $run))
            ->assertOk()
            ->assertSee(RefetchService::CANCELLED_BEFORE_FETCH_MESSAGE);
    }

    public function test_starting_a_newer_run_makes_an_older_review_read_only(): void
    {
        [$olderRun, $product] = $this->reviewRun();
        app(RefetchService::class)->createRun([$product->id], false);

        $this->get(route('options.refetch.show', $olderRun))
            ->assertOk()
            ->assertSee('A newer refetch run exists. This run is read-only.')
            ->assertDontSee('Apply All Tabs');

        Livewire::test(OptionsRefetchReview::class, ['run' => $olderRun])
            ->set('globalActions.titles', 'overwrite')
            ->call('applyTab', 'titles')
            ->assertHasErrors('run')
            ->assertNoRedirect();

        $this->assertSame('Old Title', $product->refresh()->work_name);
        $this->assertSame(RefetchRun::STATUS_REVIEW, $olderRun->refresh()->status);
    }

    public function test_apply_all_overwrites_fetched_metadata_and_preserves_user_owned_fields_and_custom_tags(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create([
            'maker_id' => 'RGOLD',
            'work_name' => 'Old JP',
            'work_name_english' => 'Old EN',
            'description' => 'Old Description',
            'description_english' => 'Old English Description',
            'series' => 'Old Series',
            'age_category' => 'ALL_AGES',
            'circle' => 'Old Circle',
            'progress' => 'Completed',
            'score' => 9,
            'start_date' => ['year' => '2025', 'month' => '01', 'day' => '02'],
            'end_date' => ['year' => '2025', 'month' => '01', 'day' => '03'],
            'notes' => 'Keep my notes',
            'priority' => '2',
            'num_re_listen_times' => 4,
            're_listen_value' => 1,
        ]);
        app(ProductContributorSync::class)->sync($product, [
            ProductContributorRole::Circle->value => ['Old Circle'],
            ProductContributorRole::Scenario->value => ['Old Scenario'],
            ProductContributorRole::VoiceActor->value => ['Old Voice'],
            ProductContributorRole::Illustration->value => ['Old Artist'],
            ProductContributorRole::Author->value => ['Old Author'],
        ], 'RGOLD');
        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_JAPANESE => Genre::resolveIdsFromTitles(['Old JP Tag']),
            Genre::LANGUAGE_ENGLISH => Genre::resolveIdsFromTitles(['Old EN Tag']),
        ], Genre::resolveIdsFromTitles(['Custom Tag']));
        $userOwnedFields = [
            'progress',
            'score',
            'start_date',
            'end_date',
            'notes',
            'priority',
            'num_re_listen_times',
            're_listen_value',
        ];
        $userOwnedValues = $product->fresh()->only($userOwnedFields);

        $run = app(RefetchService::class)->createRun([$product->id], false);
        $result = $run->results()->firstOrFail();
        $result->forceFill([
            'status' => RefetchWorkResult::STATUS_FETCHED,
            'changes' => [
                'titles' => [
                    'work_name' => ['label' => 'Japanese Title', 'old' => 'Old JP', 'new' => 'New JP'],
                    'work_name_english' => ['label' => 'English Title', 'old' => 'Old EN', 'new' => 'New EN'],
                ],
                'descriptions' => [
                    'description' => ['label' => 'Japanese Description', 'old' => 'Old Description', 'new' => 'New Description'],
                    'description_english' => ['label' => 'English Description', 'old' => 'Old English Description', 'new' => 'New English Description'],
                ],
                'series' => [
                    'series' => ['label' => 'Series', 'old' => 'Old Series', 'new' => 'New Series'],
                ],
                'age' => [
                    'age_category' => ['label' => 'Age', 'old' => 'ALL_AGES', 'new' => 'R18'],
                ],
                'circle' => [
                    'circle' => ['label' => 'Circle', 'old' => ['Old Circle'], 'new' => ['New Circle']],
                ],
                'maker' => [
                    'maker_id' => ['label' => 'Maker ID', 'old' => 'RGOLD', 'new' => 'RGNEW'],
                ],
                'scenario' => [
                    'scenario' => ['label' => 'Scenario Author', 'old' => ['Old Scenario'], 'new' => ['New Scenario']],
                ],
                'voice_actor' => [
                    'voice_actor' => ['label' => 'Voice Actor', 'old' => ['Old Voice'], 'new' => ['New Voice']],
                ],
                'illustration' => [
                    'illustration' => ['label' => 'Illustration Author', 'old' => ['Old Artist'], 'new' => ['New Artist']],
                ],
                'author' => [
                    'author' => ['label' => 'Author', 'old' => ['Old Author'], 'new' => ['New Author']],
                ],
                'tags' => [
                    'tags' => [
                        'label' => 'Fetched Tags',
                        'old' => [
                            'japanese' => ['Old JP Tag'],
                            'english' => ['Old EN Tag'],
                            'custom' => ['Custom Tag'],
                        ],
                        'new' => [
                            'japanese' => ['New JP Tag'],
                            'english' => ['New EN Tag'],
                            'custom' => ['Custom Tag'],
                        ],
                    ],
                ],
            ],
        ])->save();
        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'completed_at' => now(),
            'resolved_tabs' => ['cover', 'sample_images'],
        ])->save();
        Storage::disk('local')->put($this->stagedJsonPath($run, $product), '{"version":"new"}');

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set(
                'globalActions',
                collect(RefetchCategory::cases())
                    ->mapWithKeys(fn(RefetchCategory $category): array => [
                        $category->value => RefetchService::ACTION_OVERWRITE,
                    ])
                    ->all(),
            )
            ->call('applyAll')
            ->assertRedirectToRoute('options.refetch.show', $run);

        $product->refresh();
        $this->assertSame([
            'maker_id' => 'RGNEW',
            'work_name' => 'New JP',
            'work_name_english' => 'New EN',
            'description' => 'New Description',
            'description_english' => 'New English Description',
            'series' => 'New Series',
            'age_category' => 'R18',
            'circle' => 'New Circle',
        ], $product->only([
            'maker_id',
            'work_name',
            'work_name_english',
            'description',
            'description_english',
            'series',
            'age_category',
            'circle',
        ]));
        $this->assertSame($userOwnedValues, $product->only($userOwnedFields));
        $this->assertSame(['New JP Tag'], $product->japaneseGenres()->pluck('genres.title')->all());
        $this->assertSame(['New EN Tag'], $product->englishGenres()->pluck('genres.title')->all());
        $this->assertSame(['Custom Tag'], $product->customGenres()->pluck('genres.title')->all());
        $contributors = app(ProductContributorSync::class)->namesByRole($product);
        $this->assertSame(['New Circle'], $contributors['circle']);
        $this->assertSame(['New Scenario'], $contributors['scenario']);
        $this->assertSame(['New Voice'], $contributors['voice_actor']);
        $this->assertSame(['New Artist'], $contributors['illustration']);
        $this->assertSame(['New Author'], $contributors['author']);
        $this->assertSame(RefetchRun::STATUS_APPLIED, $run->refresh()->status);
        $this->assertSame('{"version":"new"}', Storage::disk('local')->get("Works/{$product->id}.json"));
    }

    public function test_cover_and_samples_are_applied_independently_and_reviewed_as_images(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $product = Product::factory()->create([
            'work_image' => 'storage/Works/RJ000000001/cover.jpg',
            'sample_images' => ['storage/Works/RJ000000001/sample_1.jpg'],
        ]);
        $run = app(RefetchService::class)->createRun([$product->id], true);
        $result = $run->results()->firstOrFail();
        $stage = "Refetch/{$run->id}/Works/{$product->id}";
        Storage::disk('public')->put("Works/{$product->id}/cover.jpg", 'old-cover');
        Storage::disk('public')->put("Works/{$product->id}/sample_1.jpg", 'old-sample');
        Storage::disk('public')->put("{$stage}/cover.jpg", 'new-cover');
        Storage::disk('public')->put("{$stage}/sample_1.jpg", 'new-sample');
        Storage::disk('local')->put("Refetch/{$run->id}/Works/{$product->id}.json", '{"version":"new"}');
        $result->forceFill([
            'status' => RefetchWorkResult::STATUS_FETCHED,
            'changes' => [
                'cover' => [
                    'cover' => [
                        'label' => 'Cover',
                        'old' => "storage/Works/{$product->id}/cover.jpg",
                        'new' => "storage/{$stage}/cover.jpg",
                        'staged_path' => "{$stage}/cover.jpg",
                    ],
                ],
                'sample_images' => [
                    'sample_images' => [
                        'label' => 'Sample Images',
                        'old' => ["storage/Works/{$product->id}/sample_1.jpg"],
                        'new' => ["storage/{$stage}/sample_1.jpg"],
                        'staged_paths' => ["{$stage}/sample_1.jpg"],
                    ],
                ],
            ],
        ])->save();
        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'resolved_tabs' => array_values(array_diff(
                RefetchCategory::values(),
                ['cover', 'sample_images'],
            )),
        ])->save();

        $this->get(route('options.refetch.show', $run))
            ->assertOk()
            ->assertSee('refetch-preview-images', false)
            ->assertSee(asset("storage/{$stage}/sample_1.jpg"), false);

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set('globalActions.cover', 'overwrite')
            ->call('applyTab', 'cover')
            ->assertNoRedirect();

        $this->assertSame('new-cover', Storage::disk('public')->get("Works/{$product->id}/cover.jpg"));
        $this->assertSame('old-sample', Storage::disk('public')->get("Works/{$product->id}/sample_1.jpg"));
        $this->assertSame(RefetchRun::STATUS_REVIEW, $run->refresh()->status);

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set('globalActions.sample_images', 'overwrite')
            ->call('applyTab', 'sample_images')
            ->assertNoRedirect();

        $this->assertSame('new-sample', Storage::disk('public')->get("Works/{$product->id}/sample_1.jpg"));
        $this->assertSame(RefetchRun::STATUS_APPLIED, $run->refresh()->status);
    }

    public function test_detailed_tag_choices_preserve_each_existing_tag_decision(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create();
        app(ProductGenreSync::class)->sync($product, [
            Genre::LANGUAGE_JAPANESE => Genre::resolveIdsFromTitles(['Stale JP']),
            Genre::LANGUAGE_ENGLISH => Genre::resolveIdsFromTitles(['Stale EN']),
        ], Genre::resolveIdsFromTitles(['Overlap', 'Keep Custom']));
        $run = app(RefetchService::class)->createRun([$product->id], false);
        $result = $run->results()->firstOrFail();
        $result->forceFill([
            'status' => RefetchWorkResult::STATUS_FETCHED,
            'changes' => [
                'tags' => [
                    'tags' => [
                        'label' => 'Fetched Tags',
                        'old' => [
                            'japanese' => ['Stale JP'],
                            'english' => ['Stale EN'],
                            'custom' => ['Keep Custom', 'Overlap'],
                        ],
                        'new' => [
                            'japanese' => ['Added JP', 'Overlap'],
                            'english' => ['Added EN'],
                            'custom' => ['Keep Custom', 'Overlap'],
                        ],
                        'details' => [
                            'added_japanese' => ['Added JP'],
                            'added_english' => ['Added EN'],
                            'stale_japanese' => ['Stale JP'],
                            'stale_english' => ['Stale EN'],
                            'custom_to_fetched_japanese' => ['Overlap'],
                            'custom_to_fetched_english' => [],
                        ],
                    ],
                ],
            ],
        ])->save();
        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'resolved_tabs' => array_values(array_diff(RefetchCategory::values(), ['tags'])),
        ])->save();
        Storage::disk('local')->put($this->stagedJsonPath($run, $product), '{"version":"new"}');

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set('globalActions.tags', 'ignore')
            ->set("actions.tags.{$result->id}.tags", 'detailed')
            ->set(
                "tagActions.{$result->id}",
                [
                    'added_japanese' => 'ignore',
                    'added_english' => 'add_as_fetched',
                    'stale_japanese' => 'move_to_custom',
                    'stale_english' => 'remove',
                    'custom_to_fetched' => 'keep_custom',
                ],
            )
            ->call('applyTab', 'tags')
            ->assertNoRedirect();

        $product->refresh();
        $this->assertSame([], $product->japaneseGenres()->pluck('genres.title')->all());
        $this->assertSame(['Added EN'], $product->englishGenres()->pluck('genres.title')->all());
        $this->assertEqualsCanonicalizing(
            ['Keep Custom', 'Overlap', 'Stale JP'],
            $product->customGenres()->pluck('genres.title')->all(),
        );
        $this->assertSame('detailed', data_get($result->fresh()->decisions, 'tags.tags.action'));
        $this->assertSame(RefetchRun::STATUS_APPLIED, $run->refresh()->status);
    }

    public function test_invalid_tab_key_is_rejected_by_livewire_validation(): void
    {
        [$run] = $this->reviewRun();

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->call('applyTab', 'unexpected')
            ->assertHasErrors('category')
            ->assertNoRedirect();

        $this->assertSame(RefetchRun::STATUS_REVIEW, $run->refresh()->status);
    }

    public function test_refetch_tag_colors_remain_optional_on_the_review_surface(): void
    {
        $tag = Genre::query()->create([
            'title' => 'Colored Refetch Tag',
            'color' => '#aa3366',
            'text_color' => '#ffffff',
        ]);
        [$run,, $result] = $this->reviewRun();
        $result->forceFill([
            'changes' => [
                'tags' => [
                    'tags' => [
                        'label' => 'Fetched Tags',
                        'old' => ['japanese' => [], 'english' => [], 'custom' => []],
                        'new' => ['japanese' => [$tag->title], 'english' => [], 'custom' => []],
                        'details' => ['added_japanese' => [$tag->title]],
                    ],
                ],
            ],
        ])->save();
        $run->forceFill([
            'resolved_tabs' => array_values(array_diff(RefetchCategory::values(), ['tags'])),
        ])->save();

        $this->get(route('options.refetch.show', $run))
            ->assertOk()
            ->assertDontSee('--tag-color: #aa3366;', false);

        Option::setTagColorSurfaces([
            ...Option::tagColorSurfaces(),
            Option::TAG_COLOR_SURFACE_REFETCH => true,
        ]);

        $this->get(route('options.refetch.show', $run))
            ->assertOk()
            ->assertSee('--tag-color: #aa3366;', false)
            ->assertSee('--tag-text-color: #ffffff;', false);
    }

    /**
     * @return array{RefetchRun, Product, \App\Models\RefetchWorkResult}
     */
    private function reviewRun(): array
    {
        $product = Product::factory()->create([
            'work_name' => 'Old Title',
            'description' => 'Old Description',
        ]);
        $run = app(RefetchService::class)->createRun([$product->id], false);
        $result = $run->results()->firstOrFail();
        $result->forceFill([
            'status' => \App\Models\RefetchWorkResult::STATUS_FETCHED,
            'changes' => [
                RefetchCategory::Titles->value => [
                    'work_name' => [
                        'label' => 'Japanese Title',
                        'old' => 'Old Title',
                        'new' => 'New Title',
                    ],
                ],
                RefetchCategory::Descriptions->value => [
                    'description' => [
                        'label' => 'Japanese Description',
                        'old' => 'Old Description',
                        'new' => 'New Description',
                    ],
                ],
            ],
        ])->save();
        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'completed_at' => now(),
            'resolved_tabs' => array_values(array_diff(
                RefetchCategory::values(),
                [RefetchCategory::Titles->value, RefetchCategory::Descriptions->value],
            )),
        ])->save();

        return [$run, $product, $result];
    }

    private function stagedJsonPath(RefetchRun $run, Product $product): string
    {
        return "Refetch/{$run->id}/Works/{$product->id}.json";
    }
}
