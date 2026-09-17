<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidPartialDate implements ValidationRule
{
    public function __construct(private readonly int $fallbackYear = 2000) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $month = $value['month'] ?? null;
        $day = $value['day'] ?? null;
        if ($month === null || $day === null) {
            return;
        }

        if (! checkdate((int) $month, (int) $day, (int) ($value['year'] ?? $this->fallbackYear))) {
            $fail('Invalid partial date.');
        }
    }
}
