<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class MaxBytes implements ValidationRule
{
    public function __construct(private readonly int $maxBytes) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > $this->maxBytes) {
            $fail('Description exceeds the database text byte limit.');
        }
    }
}
