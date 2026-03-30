<?php

namespace App\View\Components\Fields;

use App\Enums\ProductPriority;
use Illuminate\Contracts\View\View;

final class Priority extends EnumSelectField
{
    public function render(): View
    {
        return view('components.fields.priority');
    }

    protected function enumClass(): string
    {
        return ProductPriority::class;
    }
}
