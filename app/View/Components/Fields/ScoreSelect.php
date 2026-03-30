<?php

namespace App\View\Components\Fields;

use App\Enums\ProductScore;
use Illuminate\Contracts\View\View;

final class ScoreSelect extends EnumSelectField
{
    public function render(): View
    {
        return view('components.fields.score-select');
    }

    protected function enumClass(): string
    {
        return ProductScore::class;
    }
}
