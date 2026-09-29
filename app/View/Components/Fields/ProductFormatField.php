<?php

namespace App\View\Components\Fields;

use App\Enums\ProductFormat;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class ProductFormatField extends Component
{
    public string $value;

    public function __construct(
        public string $label,
        mixed $value = null,
    ) {
        $value = old('product_format', $value);

        $this->value = is_array($value)
            ? ProductFormat::inputValue($value)
            : trim((string) ($value ?? ''));
    }

    public function render(): View
    {
        return view('components.fields.product-format-field');
    }
}
