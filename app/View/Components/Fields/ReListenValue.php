<?php

namespace App\View\Components\Fields;

use App\Enums\ProductReListenValue;
use Illuminate\Contracts\View\View;

final class ReListenValue extends EnumSelectField
{
    public function render(): View
    {
        return view('components.fields.re-listen-value');
    }

    protected function enumClass(): string
    {
        return ProductReListenValue::class;
    }
}
