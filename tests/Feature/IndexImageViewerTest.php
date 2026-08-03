<?php

namespace Tests\Feature;

use App\Enums\ProductField;
use App\Livewire\IndexImageViewerSettings;
use App\Livewire\OptionsResetDefaults;
use App\Livewire\ProductIndex;
use App\Models\Option;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class IndexImageViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_defaults_off_and_supports_save_individual_reset_and_global_reset(): void
    {
        $this->assertFalse(Option::indexImageViewerEnabled());

        $this->get(route('options.index'))
            ->assertOk()
            ->assertSee('Image Viewer')
            ->assertSee('Open cover and sample images in the Image Viewer');

        Livewire::test(IndexImageViewerSettings::class)
            ->assertSet('enabled', false)
            ->set('enabled', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true)
            ->assertSet('notice', 'Image viewer setting saved.');

        $this->assertTrue(Option::indexImageViewerEnabled());

        Livewire::test(IndexImageViewerSettings::class)
            ->assertSet('enabled', true)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertHasNoErrors()
            ->assertSet('enabled', false)
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('notice', 'Image viewer setting reset to default.');

        $this->assertFalse(Option::indexImageViewerEnabled());
        $this->assertDatabaseMissing('options', ['key' => Option::INDEX_IMAGE_VIEWER_ENABLED]);

        Option::setIndexImageViewerEnabled(true);

        Livewire::test(OptionsResetDefaults::class, ['activeTab' => 'general'])
            ->call('resetAll')
            ->assertRedirect(route('options.index', ['tab' => 'general']));

        $this->assertFalse(Option::indexImageViewerEnabled());
        $this->assertDatabaseMissing('options', ['key' => Option::INDEX_IMAGE_VIEWER_ENABLED]);
    }

    public function test_disabled_viewer_keeps_remote_image_link_and_omits_viewer_assets(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ100000001',
            'work_name' => 'VIEWER_DISABLED_WORK',
            'work_image' => 'storage/Works/RJ100000001/cover.jpg',
        ]);
        $dlsiteUrl = $product->dlsiteWorkUrl(false);

        $component = Livewire::test(ProductIndex::class)
            ->assertSee('VIEWER_DISABLED_WORK')
            ->assertDontSee('index-image-viewer-trigger', false)
            ->assertDontSee('index-image-viewer-dialog', false);

        $this->assertSame(3, substr_count($component->html(), $dlsiteUrl));

        Livewire::flushState();

        $this->get(route('index'))
            ->assertOk()
            ->assertDontSee('index-image-viewer-dialog', false)
            ->assertDontSee('scripts/index-image-viewer.js', false);

        $action = Livewire::test(ProductIndex::class)
            ->call('workImages', $product->id)
            ->assertReturned(null);

        $this->assertSame(404, data_get($action->effects, 'returnsMeta.0.status'));
    }

    public function test_enabled_viewer_uses_a_livewire_action_and_renders_viewer_assets(): void
    {
        Storage::fake('public');
        Option::setIndexImageViewerEnabled(true);

        $paths = [
            'storage/Works/RJ100000002/cover.webp',
            'storage/Works/RJ100000002/sample_1.png',
            'storage/Works/RJ100000002/sample_2.jpg',
            'storage/Works/RJ100000002/sample_3.avif',
        ];
        $product = Product::factory()->create([
            'id' => 'RJ100000002',
            'work_name' => 'VIEWER_ENABLED_WORK',
            'work_name_english' => 'VIEWER_ENABLED_WORK_EN',
            'work_image' => $paths[0],
            'sample_images' => array_slice($paths, 1),
        ]);
        $disk = Storage::disk('public');

        foreach ($paths as $path) {
            $disk->put(substr($path, strlen('storage/')), 'image');
        }

        $versionedPaths = array_map(
            fn(string $path): string => "{$path}?v=" . filemtime(
                $disk->path(substr($path, strlen('storage/')))
            ),
            $paths,
        );
        $versionedUrls = array_map('asset', $versionedPaths);
        $dlsiteUrl = $product->dlsiteWorkUrl(false);

        $component = Livewire::test(ProductIndex::class)
            ->assertSee('class="product-link index-image-viewer-trigger"', false)
            ->assertSee('data-index-image-viewer-product="RJ100000002"', false)
            ->assertSee('data-index-image-viewer-title="RJ100000002 - VIEWER_ENABLED_WORK"', false)
            ->assertSee('index-image-viewer-dialog', false)
            ->assertSee('wire:ignore', false)
            ->assertSee($versionedPaths[0], false);

        $this->assertSame(2, substr_count($component->html(), $dlsiteUrl));

        Livewire::test(ProductIndex::class)
            ->call('workImages', $product->id)
            ->assertReturned($versionedUrls);

        Livewire::flushState();

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('data-index-image-viewer-full', false)
            ->assertSee('scripts/index-image-viewer.js', false)
            ->assertSee('View in full');
    }

    public function test_hidden_image_column_does_not_render_a_viewer_trigger(): void
    {
        Option::setIndexImageViewerEnabled(true);
        Option::setIndexFieldLayout([
            ['field' => ProductField::Image->value, 'visible' => false],
            ['field' => ProductField::Title->value, 'visible' => true],
        ]);
        Product::factory()->create(['work_name' => 'VIEWER_HIDDEN_IMAGE_WORK']);

        Livewire::flushState();

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('VIEWER_HIDDEN_IMAGE_WORK')
            ->assertDontSee('data-column="Image"', false)
            ->assertDontSee('index-image-viewer-trigger', false)
            ->assertDontSee('index-image-viewer-dialog', false)
            ->assertDontSee('scripts/index-image-viewer.js', false);
    }

    public function test_image_path_migration_replaces_remote_positions_and_preserves_safe_custom_paths(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ100000003',
            'work_image' => 'https://img.example.invalid/cover.jpg',
            'sample_images' => [
                'https://img.example.invalid/sample-one.jpg',
                '/STORAGE/WORKS/RJ100000003/SAMPLE_2.PNG',
                '../outside-three.webp',
            ],
        ]);
        $malformedProduct = Product::factory()->create([
            'id' => 'RJ100000004',
            'work_image' => null,
        ]);
        DB::table('products')
            ->where('id', $malformedProduct->id)
            ->update(['sample_images' => json_encode('malformed-shape', JSON_THROW_ON_ERROR)]);

        (require database_path('migrations/2026_07_27_000000_normalize_product_sample_image_paths.php'))->up();

        $product->refresh();

        $this->assertSame('storage/Works/RJ100000003/cover.jpg', $product->work_image);
        $this->assertSame([
            'storage/Works/RJ100000003/sample_1.jpg',
            'storage/Works/RJ100000003/sample_2.png',
            'storage/Works/RJ100000003/sample_3.jpg',
        ], $product->sample_images);

        $malformedProduct->refresh();

        $this->assertSame('storage/Works/RJ100000004/cover.jpg', $malformedProduct->work_image);
        $this->assertSame([], $malformedProduct->sample_images);
    }
}
