<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class GraduationYear implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // This is an optional YEAR column, even when the unit renders it as text.
        if ($value === null || $value === '') {
            return;
        }

        $year = (is_string($value) || is_int($value) || is_float($value))
            ? (string) $value
            : '';

        if (! preg_match('/\A[0-9]{4}\z/', $year)
            || (int) $year < 2000
            || (int) $year > now()->year + 2) {
            $fail('Tahun Lulus harus berupa 4 digit tahun antara 2000 dan '.(now()->year + 2).', misalnya 2026.');
        }
    }
}
