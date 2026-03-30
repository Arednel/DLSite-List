<?php

namespace Tests\Unit\View\Components\Fields;

use App\Enums\ProductPriority;
use App\Enums\ProductProgress;
use App\Enums\ProductReListenValue;
use App\Enums\ProductScore;
use App\View\Components\Fields\Priority;
use App\View\Components\Fields\ReListenValue;
use App\View\Components\Fields\ScoreSelect;
use App\View\Components\Fields\StatusSelect;
use Tests\TestCase;

class EnumSelectFieldTest extends TestCase
{
    public function test_status_select_uses_enum_options_and_default_value(): void
    {
        $component = new StatusSelect();

        $this->assertSame(ProductProgress::PlanToListen->value, $component->value);
        $this->assertSame(ProductProgress::options(), $component->options);
    }

    public function test_numeric_select_components_use_matching_enum_options(): void
    {
        $score = new ScoreSelect('9');
        $priority = new Priority('2');
        $reListenValue = new ReListenValue('5');

        $this->assertSame('9', $score->value);
        $this->assertSame(ProductScore::options(), $score->options);

        $this->assertSame('2', $priority->value);
        $this->assertSame(ProductPriority::options(), $priority->options);

        $this->assertSame('5', $reListenValue->value);
        $this->assertSame(ProductReListenValue::options(), $reListenValue->options);
    }
}
