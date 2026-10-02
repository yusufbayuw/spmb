<?php

namespace App\Services\PracticalValidators;

class PracticalValue
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        $lower = strtolower($trimmed);

        return match (true) {
            $lower === 'true' => true,
            $lower === 'false' => false,
            $lower === 'null' => null,
            is_numeric($trimmed) => str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed,
            default => $trimmed,
        };
    }

    public static function equivalent(mixed $expected, mixed $actual): bool
    {
        return self::normalize($expected) === self::normalize($actual);
    }
}
