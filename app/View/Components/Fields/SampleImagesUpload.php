<?php

namespace App\View\Components\Fields;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class SampleImagesUpload extends Component
{
    /**
     * @var list<array{path: string, url: string, name: string}>
     */
    public array $samples;

    public function __construct(
        public ?Product $product = null,
        public bool $editable = true,
        public string $viewerTitle = '',
    ) {
        $productSamples = array_values($product?->sample_images ?? []);
        $oldOrder = old('sample_image_order');
        $restoreOldOrder = $editable
            && $product !== null
            && ($oldOrder !== null || old('sample_images_submitted') !== null);

        $samplePaths = $restoreOldOrder
            ? collect(is_array($oldOrder) ? $oldOrder : [])
            ->filter(fn(mixed $item): bool => is_string($item) && str_starts_with($item, 'existing:'))
            ->map(fn(string $item): string => substr($item, 9))
            ->filter(fn(string $path): bool => in_array($path, $productSamples, true))
            ->unique()
            ->values()
            ->all()
            : $productSamples;

        $this->samples = array_map(
            fn(string $path): array => [
                'path' => $path,
                'url' => asset(Product::versionedImagePath($path)),
                'name' => basename($path),
            ],
            $samplePaths,
        );
    }

    public function render(): View
    {
        return view('components.fields.sample-images-upload');
    }
}
