<?php

namespace App\Services;

/**
 * Normalizes school labels for export grouping only.
 *
 * This service does not change Registration::previous_school or any other
 * stored applicant data. Inexact/fuzzy school matching is deliberately avoided.
 */
final class SchoolNameNormalizer
{
    public const MISSING_LABEL = 'TIDAK DIISI';

    public function normalize(?string $name): string
    {
        if ($name === null) {
            return self::MISSING_LABEL;
        }

        // Collapse whitespace (including non-breaking spaces), punctuation and
        // case variations without making assumptions about school identity.
        $normalized = preg_replace(
            '/[^\p{L}\p{N}]+/u',
            ' ',
            mb_strtoupper($name, 'UTF-8'),
        );

        if ($normalized === null) {
            return self::MISSING_LABEL;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);

        if ($normalized === '') {
            return self::MISSING_LABEL;
        }

        // SMPN 2 / SMPN2 / SMP N 2 / SMP NEGERI 2 are the same abbreviation.
        // Do not guess a city for incomplete names such as "SMPN2".
        $normalized = preg_replace('/^SMP\s*N(?=\s*\d)/u', 'SMP NEGERI ', $normalized) ?? $normalized;
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);

        // Explicitly reviewed variants from the sample report. No fuzzy search:
        // similar names can refer to separate schools or branches.
        $aliases = config('registration_school_recap.aliases', []);
        $canonical = is_array($aliases) ? ($aliases[$normalized] ?? null) : null;

        return is_string($canonical) && $canonical !== ''
            ? $canonical
            : $normalized;
    }
}
