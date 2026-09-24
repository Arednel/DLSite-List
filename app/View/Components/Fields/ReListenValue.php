<?php

namespace App\View\Components\Fields;

use App\Enums\ProductField;
use App\Enums\ProductReListenValue;
use App\Support\ContentTerminology;
use Illuminate\Contracts\View\View;

final class ReListenValue extends EnumSelectField
{
    public string $label;

    public function __construct(mixed $value = null, ?array $options = null, ?string $label = null)
    {
        parent::__construct($value, $options);

        $this->label = $label ?? ProductField::ReListenValue->label();
    }

    public function render(): View
    {
        return view('components.fields.re-listen-value', [
            'placeholder' => app(ContentTerminology::class)->repeatValueSelect(),
        ]);
    }

    protected function enumClass(): string
    {
        return ProductReListenValue::class;
    }
}
