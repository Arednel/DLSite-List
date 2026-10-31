<?php

namespace Tests\Feature;

use App\Enums\ProductField;
use App\Jobs\ImportBulkWorkJob;
use App\Models\Option;
use App\Models\Product;
use App\Support\BulkImport\BulkImportService;
use App\Support\DLSite\DLSiteProductImporter;
use App\Support\DLSite\DLSiteProductImportInput;
use App\Support\ProductContributorSync;
use App\Support\Refetch\RefetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TranslatedWorkCircleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public static function importModes(): array
    {
        return [
            'Quick Add resolution' => ['quick', false],
            'Bulk Import override' => ['bulk', true],
        ];
    }

    #[DataProvider('importModes')]
    public function test_import_resolves_original_circle_and_preserves_user_overrides(string $mode, bool $override): void
    {
        $this->fakeTranslatedFetch();
        $values = $override ? ['circle' => 'My circle', 'maker_id' => 'RG123'] : [];
        if ($mode === 'quick') {
            Option::setQuickAddFieldLayout([
                ['field' => ProductField::ProductCode->value, 'visible' => true],
                ['field' => ProductField::Circle->value, 'visible' => true],
            ]);
            $this->post('/store', ['id' => 'RJ01689827', ...$values])->assertSessionHasNoErrors();
        } else {
            $run = app(BulkImportService::class)->start(['RJ01689827'], new DLSiteProductImportInput(
                values: $values,
                visibleFields: [ProductField::ProductCode->value, ProductField::Circle->value],
                submitted: $override ? ['circle' => true, 'maker_id' => true] : [],
                autoSeriesFromTitleName: false,
            ));
            (new ImportBulkWorkJob($run->items()->firstOrFail()->id))->handle(app(DLSiteProductImporter::class));
        }

        $product = Product::findOrFail('RJ01689827');
        $this->assertSame($override ? 'My circle' : 'RaRo', $product->circle);
        $this->assertSame($override ? 'RG123' : 'RG49556', $product->maker_id);
        $this->assertSame([$product->circle], app(ProductContributorSync::class)->namesByRole($product)['circle']);
        $this->assertSame('Translated title', $product->work_name);
        $this->assertSame('Provider', Storage::disk('local')->json('Works/RJ01689827.json')['japanese']['circle']);
        Storage::disk('local')->assertExists('Works/RJ01689827 - RJ01647180.json');
    }

    public function test_refetch_proposes_original_circle_and_maker_without_applying_them(): void
    {
        $this->fakeTranslatedFetch();
        $product = Product::factory()->create(['id' => 'RJ01689827', 'circle' => 'Provider', 'maker_id' => 'RG60289']);
        app(ProductContributorSync::class)->sync($product, ['circle' => ['Provider']], 'RG60289');
        $service = app(RefetchService::class);
        $run = $service->createRun([$product->id], false);
        $result = $run->results()->firstOrFail();
        $service->fetchAndRecordResult($result);

        $changes = $result->fresh()->changes;
        $this->assertSame(['RaRo'], $changes['circle']['circle']['new']);
        $this->assertSame('RG49556', $changes['maker']['maker_id']['new']);
        $this->assertSame('Provider', $product->fresh()->circle);
        Storage::disk('local')->assertExists("Refetch/{$run->id}/Works/{$product->id} - RJ01647180.json");
        Storage::disk('local')->assertMissing("Works/{$product->id} - RJ01647180.json");
    }

    private function fakeTranslatedFetch(): void
    {
        Process::fake(function ($process) {
            $command = $process->command;
            $id = $command[array_search('--work-id', $command) + 1];
            $path = $command[array_search('--json-output', $command) + 1];
            $locale = $id === 'RJ01689827' ? [
                'product_id' => $id,
                'maker_id' => 'RG60289',
                'maker_name' => 'Provider',
                'circle' => 'Provider',
                'work_name' => 'Translated title',
                'language_editions' => [
                    ['workno' => 'RJ01647180', 'display_order' => 1],
                    ['workno' => 'RJ01689827', 'display_order' => 2],
                ],
                'translation_info' => ['original_workno' => null],
            ] : ['product_id' => $id, 'maker_id' => 'RG49556', 'maker_name' => 'RaRo'];
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, json_encode(['japanese' => $locale, 'english' => $locale]));

            return Process::result(output: json_encode(['product_id' => $id, 'downloaded_images' => [], 'failed_images' => []]));
        })->preventStrayProcesses();
    }
}
