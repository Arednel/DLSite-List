<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\Product;
use App\Support\LibraryMutationLock;
use App\Support\ProductImagePromotion;
use App\Support\ProductImageSubmission;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductImageEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_create_uses_browser_local_image_inputs_without_server_staging(): void
    {
        $response = $this->get(route('products.create.custom'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="work_image"', false)
            ->assertDontSee('name="remove_work_image"', false)
            ->assertDontSee('name="sample_images_submitted"', false)
            ->assertSee('scripts/product-image-editor.js', false)
            ->assertSee('index-image-viewer-dialog', false);

        $xpath = $this->responseXPath($response);

        $this->assertCount(1, $xpath->query('//*[@data-product-image-editor and @data-product-image-editor-kind="cover"]'));
        $this->assertCount(1, $xpath->query('//*[@data-product-image-editor and @data-product-image-editor-kind="samples"]'));
        $this->assertCount(1, $xpath->query('//input[@type="file" and @name="sample_images[]" and @multiple and @data-image-editor-sample-input]'));
        $this->assertCount(0, $xpath->query('//input[@type="file" and @required]'));
    }

    public function test_cover_editor_only_shows_actions_for_the_current_cover_state(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'image', 'visible' => true, 'editable' => true],
        ]);

        $coverless = Product::factory()->create([
            'id' => 'RJ123468',
            'work_image' => null,
        ]);
        $coverlessResponse = $this->get(route('products.edit', $coverless))->assertOk();
        $coverlessXPath = $this->responseXPath($coverlessResponse);
        $this->assertCount(1, $coverlessXPath->query('//*[@data-image-editor-cover-preview and @hidden]'));
        $this->assertCount(1, $coverlessXPath->query('//input[@type="file" and @data-image-editor-cover-input]'));
        $this->assertCount(1, $coverlessXPath->query('//*[@data-image-editor-cover-picker and contains(concat(" ", normalize-space(@class), " "), " image-editor-native-picker--empty ")]'));
        $this->assertCount(0, $coverlessXPath->query('//*[@data-image-editor-cover-add-label or @data-image-editor-cover-replace-label]'));

        $covered = Product::factory()->create([
            'id' => 'RJ123469',
            'work_image' => 'storage/Works/RJ123469/cover.jpg',
        ]);
        $coveredResponse = $this->get(route('products.edit', $covered))->assertOk();
        $coveredXPath = $this->responseXPath($coveredResponse);
        $this->assertCount(0, $coveredXPath->query('//*[@data-image-editor-cover-preview and @hidden]'));
        $this->assertCount(1, $coveredXPath->query('//input[@type="file" and @data-image-editor-cover-input]'));
        $this->assertCount(0, $coveredXPath->query('//*[@data-image-editor-cover-picker and contains(concat(" ", normalize-space(@class), " "), " image-editor-native-picker--empty ")]'));
        $this->assertCount(0, $coveredXPath->query('//*[@data-image-editor-cover-add-label or @data-image-editor-cover-replace-label]'));
    }

    public function test_edit_restores_existing_image_removal_and_order_from_old_input(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'image', 'visible' => true, 'editable' => true],
            ['field' => 'sample_images', 'visible' => true, 'editable' => true],
        ]);
        $product = Product::factory()->create([
            'id' => 'RJ123470',
            'work_image' => 'storage/Works/RJ123470/cover.jpg',
            'sample_images' => [
                'storage/Works/RJ123470/sample_1.jpg',
                'storage/Works/RJ123470/sample_2.jpg',
            ],
        ]);

        $response = $this->withSession(['_old_input' => [
            'remove_work_image' => '1',
            'sample_images_submitted' => '1',
            'sample_image_order' => [
                'existing:storage/Works/RJ123470/sample_2.jpg',
                'new:99',
            ],
        ]])->get(route('products.edit', $product))->assertOk();

        $xpath = $this->responseXPath($response);
        $this->assertCount(1, $xpath->query('//*[@data-image-editor-cover-preview and @hidden]'));
        $this->assertCount(1, $xpath->query('//input[@type="file" and @data-image-editor-cover-input]'));
        $this->assertCount(1, $xpath->query('//*[@data-image-editor-cover-picker and contains(concat(" ", normalize-space(@class), " "), " image-editor-native-picker--empty ")]'));
        $this->assertCount(0, $xpath->query('//*[@data-image-editor-cover-add-label or @data-image-editor-cover-replace-label]'));
        $response
            ->assertSee('name="remove_work_image" value="1"', false)
            ->assertSee('name="sample_image_order[]" value="existing:storage/Works/RJ123470/sample_2.jpg"', false)
            ->assertDontSee('name="sample_image_order[]" value="existing:storage/Works/RJ123470/sample_1.jpg"', false)
            ->assertDontSee('new:99', false);
    }

    public function test_custom_create_saves_optional_cover_and_samples_on_final_submit(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $cover = UploadedFile::fake()->image('cover.png');
        $sampleOne = UploadedFile::fake()->image('one.jpg');
        $sampleTwo = UploadedFile::fake()->image('two.png');
        $coverBytes = file_get_contents($cover->getRealPath());
        $secondBytes = file_get_contents($sampleTwo->getRealPath());

        $this->post(route('products.store.custom'), [
            'id' => 'RJ123456',
            'work_name' => 'Browser image editor',
            'age_category' => 'ALL_AGES',
            'work_image' => $cover,
            'sample_images' => ['11' => $sampleOne, '22' => $sampleTwo],
            'sample_image_order' => ['new:22', 'new:11'],
        ])->assertRedirect('/#RJ123456')->assertSessionHasNoErrors();
        $this->recoverCommittedImagePromotion();

        $product = Product::findOrFail('RJ123456');
        $this->assertSame('storage/Works/RJ123456/cover.png', $product->work_image);
        $this->assertSame([
            'storage/Works/RJ123456/sample_1.png',
            'storage/Works/RJ123456/sample_2.jpg',
        ], $product->sample_images);
        $this->assertSame($coverBytes, Storage::disk('public')->get('Works/RJ123456/cover.png'));
        $this->assertSame($secondBytes, Storage::disk('public')->get('Works/RJ123456/sample_1.png'));
        Storage::disk('local')->assertMissing('ImagePromotions/active.json');
    }

    public function test_custom_create_can_save_without_any_images(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->post(route('products.store.custom'), [
            'id' => 'RJ123457',
            'work_name' => 'Coverless work',
            'age_category' => 'ALL_AGES',
        ])->assertRedirect('/#RJ123457')->assertSessionHasNoErrors();

        $product = Product::findOrFail('RJ123457');
        $this->assertNull($product->work_image);
        $this->assertSame([], $product->sample_images);
        $this->assertSame([], Storage::disk('public')->allFiles('Works'));
    }

    public function test_custom_create_ignores_submitted_cover_when_field_is_hidden(): void
    {
        Option::setCustomQuickAddFieldLayout([
            ['field' => 'image', 'visible' => false],
        ]);
        Storage::fake('local');
        Storage::fake('public');

        $this->post(route('products.store.custom'), [
            'id' => 'RJ123473',
            'work_name' => 'Hidden cover upload',
            'age_category' => 'ALL_AGES',
            'work_image' => UploadedFile::fake()->image('cover.png')->size(20481),
        ])->assertRedirect('/#RJ123473')->assertSessionHasNoErrors();

        $this->assertNull(Product::findOrFail('RJ123473')->work_image);
        $this->assertSame([], Storage::disk('public')->allFiles('Works'));
    }

    public function test_custom_create_recovers_an_interrupted_image_promotion_before_checking_destination_conflicts(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $productId = 'RJ123466';
        $destination = "Works/{$productId}/cover.png";
        $token = bin2hex(random_bytes(16));
        Storage::disk('public')->put($destination, 'uncommitted-image');
        Storage::disk('local')->put('ImagePromotions/active.json', json_encode([
            'token' => $token,
            'owner' => 'mutation',
            'id' => null,
            'entries' => [['destination' => $destination, 'backup' => null]],
            'products' => [$productId],
        ], JSON_THROW_ON_ERROR));

        $cover = UploadedFile::fake()->image('cover.png');
        $coverBytes = file_get_contents($cover->getRealPath());
        $request = Request::create('/create', 'POST', [], [], ['work_image' => $cover]);

        app(ProductImageSubmission::class)->create(
            $request,
            $productId,
            true,
            true,
            fn(array $images): Product => Product::factory()->create([
                'id' => $productId,
                ...$images,
            ]),
        );
        $this->recoverCommittedImagePromotion();

        $this->assertSame("storage/{$destination}", Product::findOrFail($productId)->work_image);
        $this->assertSame($coverBytes, Storage::disk('public')->get($destination));
        Storage::disk('local')->assertMissing('ImagePromotions/active.json');
    }

    public function test_custom_create_reports_sample_destination_collisions_on_sample_images(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $productId = 'RJ123471';
        Storage::disk('public')->put("Works/{$productId}/sample_1.jpg", 'leftover');
        $sample = UploadedFile::fake()->image('sample.jpg');
        $request = Request::create('/create', 'POST', [
            'sample_image_order' => ['new:1'],
        ], [], [
            'sample_images' => ['1' => $sample],
        ]);

        try {
            app(ProductImageSubmission::class)->create(
                $request,
                $productId,
                true,
                true,
                fn(array $images): Product => Product::factory()->create([
                    'id' => $productId,
                    ...$images,
                ]),
            );
            $this->fail('Expected a sample destination collision.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sample_images', $exception->errors());
            $this->assertArrayNotHasKey('work_image', $exception->errors());
        }
    }

    public function test_edit_can_replace_cover_and_mix_existing_and_new_samples_in_any_order(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'image', 'visible' => true, 'editable' => true],
            ['field' => 'sample_images', 'visible' => true, 'editable' => true],
        ]);
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('Works/RJ123458/cover.jpg', 'old-cover');
        Storage::disk('public')->put('Works/RJ123458/sample_1.jpg', 'one');
        Storage::disk('public')->put('Works/RJ123458/sample_2.jpg', 'two');
        $product = Product::factory()->create([
            'id' => 'RJ123458',
            'work_image' => 'storage/Works/RJ123458/cover.jpg',
            'sample_images' => [
                'storage/Works/RJ123458/sample_1.jpg',
                'storage/Works/RJ123458/sample_2.jpg',
            ],
        ]);
        $cover = UploadedFile::fake()->image('replacement.png');
        $sample = UploadedFile::fake()->image('added.png');
        $coverBytes = file_get_contents($cover->getRealPath());
        $sampleBytes = file_get_contents($sample->getRealPath());

        $this->post(route('products.update', $product), [
            'work_name' => $product->work_name,
            'work_name_english' => $product->work_name_english,
            'work_image' => $cover,
            'sample_images' => ['7' => $sample],
            'sample_image_order' => [
                'existing:storage/Works/RJ123458/sample_2.jpg',
                'new:7',
                'existing:storage/Works/RJ123458/sample_1.jpg',
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->recoverCommittedImagePromotion();

        $product->refresh();
        $this->assertSame('storage/Works/RJ123458/cover.png', $product->work_image);
        $this->assertSame([
            'storage/Works/RJ123458/sample_2.jpg',
            'storage/Works/RJ123458/sample_3.png',
            'storage/Works/RJ123458/sample_1.jpg',
        ], $product->sample_images);
        Storage::disk('public')->assertMissing('Works/RJ123458/cover.jpg');
        $this->assertSame($coverBytes, Storage::disk('public')->get('Works/RJ123458/cover.png'));
        $this->assertSame($sampleBytes, Storage::disk('public')->get('Works/RJ123458/sample_3.png'));
        $this->assertSame('one', Storage::disk('public')->get('Works/RJ123458/sample_1.jpg'));
        $this->assertSame('two', Storage::disk('public')->get('Works/RJ123458/sample_2.jpg'));
    }

    public function test_edit_can_remove_cover_and_individual_samples(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'image', 'visible' => true, 'editable' => true],
            ['field' => 'sample_images', 'visible' => true, 'editable' => true],
        ]);
        Storage::fake('local');
        Storage::fake('public');
        foreach (['cover.jpg', 'sample_1.jpg', 'sample_2.jpg'] as $file) {
            Storage::disk('public')->put("Works/RJ123459/{$file}", $file);
        }
        $product = Product::factory()->create([
            'id' => 'RJ123459',
            'work_image' => 'storage/Works/RJ123459/cover.jpg',
            'sample_images' => [
                'storage/Works/RJ123459/sample_1.jpg',
                'storage/Works/RJ123459/sample_2.jpg',
            ],
        ]);

        $this->post(route('products.update', $product), [
            'work_name' => $product->work_name,
            'work_name_english' => $product->work_name_english,
            'remove_work_image' => '1',
            'sample_image_order' => ['existing:storage/Works/RJ123459/sample_2.jpg'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->recoverCommittedImagePromotion();

        $product->refresh();
        $this->assertNull($product->work_image);
        $this->assertSame(['storage/Works/RJ123459/sample_2.jpg'], $product->sample_images);
        Storage::disk('public')->assertMissing('Works/RJ123459/cover.jpg');
        Storage::disk('public')->assertMissing('Works/RJ123459/sample_1.jpg');
        Storage::disk('public')->assertExists('Works/RJ123459/sample_2.jpg');
    }

    public function test_edit_can_remove_all_samples(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'sample_images', 'visible' => true, 'editable' => true],
        ]);
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('Works/RJ123465/sample_1.jpg', 'sample');
        $product = Product::factory()->create([
            'id' => 'RJ123465',
            'sample_images' => ['storage/Works/RJ123465/sample_1.jpg'],
        ]);

        $this->post(route('products.update', $product), [
            'work_name' => $product->work_name,
            'work_name_english' => $product->work_name_english,
            'sample_images_submitted' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->recoverCommittedImagePromotion();

        $this->assertSame([], $product->fresh()->sample_images);
        Storage::disk('public')->assertMissing('Works/RJ123465/sample_1.jpg');
    }

    public function test_edit_form_renders_existing_images_as_ordered_browser_editor_cards(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'image', 'visible' => true, 'editable' => true],
            ['field' => 'sample_images', 'visible' => true, 'editable' => true],
        ]);
        $product = Product::factory()->create([
            'id' => 'RJ123460',
            'work_image' => 'storage/Works/RJ123460/cover.jpg',
            'sample_images' => [
                'storage/Works/RJ123460/sample_2.jpg',
                'storage/Works/RJ123460/sample_1.jpg',
            ],
        ]);

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="work_image"', false)
            ->assertSee('name="sample_images_submitted" value="1"', false)
            ->assertSee('name="sample_image_order[]" value="existing:storage/Works/RJ123460/sample_2.jpg"', false)
            ->assertSeeInOrder(['sample_2.jpg', 'sample_1.jpg'])
            ->assertSee('data-image-editor-drag-handle', false)
            ->assertSee('data-image-editor-remove-sample', false);
    }

    public function test_readonly_image_fields_show_images_but_ignore_submitted_image_changes(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'image', 'visible' => true, 'editable' => false],
            ['field' => 'sample_images', 'visible' => true, 'editable' => false],
        ]);
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('Works/RJ123461/cover.jpg', 'cover');
        Storage::disk('public')->put('Works/RJ123461/sample_1.jpg', 'sample');
        $product = Product::factory()->create([
            'id' => 'RJ123461',
            'work_image' => 'storage/Works/RJ123461/cover.jpg',
            'sample_images' => ['storage/Works/RJ123461/sample_1.jpg'],
        ]);

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('cover.jpg')
            ->assertSee('sample_1.jpg')
            ->assertDontSee('name="work_image"', false)
            ->assertDontSee('name="sample_images[]"', false)
            ->assertDontSee('data-image-editor-remove-sample', false);

        $this->post(route('products.update', $product), [
            'work_name' => $product->work_name,
            'work_name_english' => $product->work_name_english,
            'remove_work_image' => '1',
            'work_image' => UploadedFile::fake()->create('attempt.txt', 1, 'text/plain'),
            'sample_images' => [UploadedFile::fake()->create('attempt.txt', 1, 'text/plain')],
            'sample_image_order' => ['not-a-valid-image-order'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('storage/Works/RJ123461/cover.jpg', $product->work_image);
        $this->assertSame(['storage/Works/RJ123461/sample_1.jpg'], $product->sample_images);
    }

    public function test_edit_without_image_changes_does_not_wait_for_library_image_lock(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'image', 'visible' => true, 'editable' => true],
            ['field' => 'sample_images', 'visible' => true, 'editable' => true],
        ]);
        $product = Product::factory()->create(['sample_images' => []]);

        app(LibraryMutationLock::class)->run(fn() => $this->post(route('products.update', $product), [
            'work_name' => $product->work_name,
            'work_name_english' => $product->work_name_english,
            'sample_image_order' => [],
        ])->assertRedirect()->assertSessionHasNoErrors());
    }

    public function test_edit_sample_reordering_does_not_wait_for_library_image_lock(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'sample_images', 'visible' => true, 'editable' => true],
        ]);
        $product = Product::factory()->create([
            'id' => 'RJ123467',
            'sample_images' => [
                'storage/Works/RJ123467/sample_1.jpg',
                'storage/Works/RJ123467/sample_2.jpg',
            ],
        ]);

        app(LibraryMutationLock::class)->run(fn() => $this->post(route('products.update', $product), [
            'work_name' => $product->work_name,
            'work_name_english' => $product->work_name_english,
            'sample_image_order' => [
                'existing:storage/Works/RJ123467/sample_2.jpg',
                'existing:storage/Works/RJ123467/sample_1.jpg',
            ],
        ])->assertRedirect()->assertSessionHasNoErrors());

        $this->assertSame([
            'storage/Works/RJ123467/sample_2.jpg',
            'storage/Works/RJ123467/sample_1.jpg',
        ], $product->fresh()->sample_images);
    }

    public function test_sample_only_image_lock_error_is_attached_to_sample_images(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'sample_images', 'visible' => true, 'editable' => true],
        ]);
        $product = Product::factory()->create([
            'id' => 'RJ123471',
            'sample_images' => [],
        ]);

        app(LibraryMutationLock::class)->run(fn() => $this->post(route('products.update', $product), [
            'work_name' => $product->work_name,
            'work_name_english' => $product->work_name_english,
            'sample_images' => [UploadedFile::fake()->image('sample.jpg')],
        ])->assertSessionHasErrors(['sample_images'])
            ->assertSessionDoesntHaveErrors(['work_image']));
    }

    public function test_same_extension_cover_replacement_lock_error_is_attached_to_work_image(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'image', 'visible' => true, 'editable' => true],
        ]);
        $product = Product::factory()->create([
            'id' => 'RJ123472',
            'work_image' => 'storage/Works/RJ123472/cover.jpg',
        ]);

        app(LibraryMutationLock::class)->run(fn() => $this->post(route('products.update', $product), [
            'work_name' => $product->work_name,
            'work_name_english' => $product->work_name_english,
            'work_image' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertSessionHasErrors(['work_image'])
            ->assertSessionDoesntHaveErrors(['sample_images']));
    }

    public function test_edit_rejects_existing_sample_paths_that_do_not_belong_to_the_work(): void
    {
        Option::setEditFieldLayout([
            ['field' => 'sample_images', 'visible' => true, 'editable' => true],
        ]);
        $product = Product::factory()->create([
            'sample_images' => ['storage/Works/' . Product::factory()->make()->id . '/sample_1.jpg'],
        ]);

        $this->from(route('products.edit', $product))->post(route('products.update', $product), [
            'work_name' => $product->work_name,
            'sample_image_order' => ['existing:storage/Works/RJ999999999/sample_1.jpg'],
        ])->assertRedirect(route('products.edit', $product))
            ->assertSessionHasErrors(['sample_image_order']);
    }

    public function test_image_mutation_recovery_distinguishes_uncommitted_and_committed_crashes(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $product = Product::factory()->create([
            'id' => 'RJ123462',
            'work_image' => 'storage/Works/RJ123462/cover.jpg',
            'sample_images' => [],
        ]);
        $promotion = app(ProductImagePromotion::class);

        foreach ([false, true] as $committed) {
            $token = bin2hex(random_bytes(16));
            $destination = 'Works/RJ123462/cover.jpg';
            $backup = "ImagePromotions/{$token}/{$destination}";
            Storage::disk('local')->put($backup, 'old-image');
            Storage::disk('public')->put($destination, 'new-image');
            Storage::disk('local')->put('ImagePromotions/active.json', json_encode([
                'token' => $token,
                'owner' => 'mutation',
                'id' => null,
                'entries' => [['destination' => $destination, 'backup' => $backup]],
                'products' => [$product->id],
            ], JSON_THROW_ON_ERROR));

            if ($committed) {
                DB::table('options')->insert([
                    'key' => '_image_mutation_promotion_commit',
                    'value' => $token,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            app(LibraryMutationLock::class)->run(fn() => $promotion->recover());

            $this->assertSame($committed ? 'new-image' : 'old-image', Storage::disk('public')->get($destination));
            Storage::disk('local')->assertMissing('ImagePromotions/active.json');
            $this->assertDatabaseMissing('options', ['key' => '_image_mutation_promotion_commit', 'value' => $token]);
        }
    }

    public function test_failed_image_save_restores_replaced_files_and_rolls_back_database_changes(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('Works/RJ123463/cover.jpg', 'original-cover');
        $product = Product::factory()->create([
            'id' => 'RJ123463',
            'work_image' => 'storage/Works/RJ123463/cover.jpg',
            'sample_images' => [],
            'notes' => 'before',
        ]);
        $upload = UploadedFile::fake()->image('cover.jpg');
        $request = Request::create('/update/RJ123463', 'POST', [], [], ['work_image' => $upload]);

        try {
            app(ProductImageSubmission::class)->update(
                $request,
                $product,
                true,
                true,
                function (array $images) use ($product): void {
                    $product->forceFill([...$images, 'notes' => 'after'])->save();
                    throw new \RuntimeException('Simulated save failure.');
                },
            );
            $this->fail('Expected the simulated save failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated save failure.', $exception->getMessage());
        }

        $product->refresh();
        $this->assertSame('before', $product->notes);
        $this->assertSame('storage/Works/RJ123463/cover.jpg', $product->work_image);
        $this->assertSame('original-cover', Storage::disk('public')->get('Works/RJ123463/cover.jpg'));
        Storage::disk('local')->assertMissing('ImagePromotions/active.json');
    }

    public function test_invalid_uploaded_images_are_rejected_by_normal_form_validation(): void
    {
        $response = $this->from(route('products.create.custom'))->post(route('products.store.custom'), [
            'id' => 'RJ123464',
            'work_name' => 'Invalid image',
            'age_category' => 'ALL_AGES',
            'work_image' => UploadedFile::fake()->create('cover.txt', 1, 'text/plain'),
            'sample_images' => [UploadedFile::fake()->image('too-large.png')->size(20481)],
        ]);

        $response->assertRedirect(route('products.create.custom'))
            ->assertSessionHasErrors(['work_image', 'sample_images.0']);
    }

    private function responseXPath(TestResponse $response): DOMXPath
    {
        $response->assertOk();

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());

        return new DOMXPath($document);
    }

    private function recoverCommittedImagePromotion(): void
    {
        app(LibraryMutationLock::class)->run(
            fn() => app(ProductImagePromotion::class)->recover(),
        );
    }
}
