<?php

namespace App\View\Components\Fields;

use App\Enums\ProductProgress;
use Illuminate\Contracts\View\View;

final class StatusSelect extends EnumSelectField
{
    public function render(): View
    {
        return view('components.fields.status-select');
    }

    protected function enumClass(): string
    {
        return ProductProgress::class;
    }

    protected function defaultValue(): mixed
    {
        return ProductProgress::PlanToListen->value;
    }
}
