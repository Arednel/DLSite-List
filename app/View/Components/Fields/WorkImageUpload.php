<?php

namespace App\View\Components\Fields;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class WorkImageUpload extends Component
{
    public bool $coverRemoved;

    public ?string $coverUrl;

    public function __construct(
        public ?Product $product = null,
        public bool $editable = true,
        public string $viewerTitle = '',
    ) {
        $this->coverRemoved = $editable
            && $product !== null
            && filter_var(old('remove_work_image', false), FILTER_VALIDATE_BOOL);

        $coverPath = $this->coverRemoved ? null : $product?->work_image;
        $this->coverUrl = blank($coverPath) ? null : asset(Product::versionedImagePath($coverPath));
    }

    public function render(): View
    {
        return view('components.fields.work-image-upload');
    }
}
