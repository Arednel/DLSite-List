<?php

namespace App\Rules;

use App\Enums\ProductFormat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidProductFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! ProductFormat::isValidStoredValue($value)) {
            $fail(__('The Product Format value is invalid.'));
        }
    }
}
