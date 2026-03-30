<?php

namespace App\View\Components\Fields;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

abstract class EnumSelectField extends Component
{
    public array $options;

    public mixed $value;

    public function __construct(mixed $value = null)
    {
        $this->value = $value ?? $this->defaultValue();
        $this->options = $this->resolveOptions();
    }

    abstract public function render(): View;

    /**
     * @return class-string
     */
    abstract protected function enumClass(): string;

    protected function defaultValue(): mixed
    {
        return null;
    }

    protected function resolveOptions(): array
    {
        $enumClass = $this->enumClass();

        return $enumClass::options();
    }
}
