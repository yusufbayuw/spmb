<?php

namespace App\Support;

/**
 * User-facing admission terminology. Internal class names, database keys and
 * previously stored audit/event identifiers intentionally remain unchanged.
 */
final class AdmissionTerminology
{
    public static function label(): string
    {
        return SpmbOperationalMode::profile()['portal_label'];
    }

    public static function applicationName(?string $configuredName = null): string
    {
        $name = trim((string) $configuredName);
        if ($name === '') {
            return self::label();
        }

        $hasAdmissionAcronym = preg_match(
            '/(?<![\pL\pN])(?:SPMB|PMB)(?![\pL\pN])/iu',
            $name,
        ) === 1;

        $name = self::visibleText($name);

        // Keep the institutional/white-label name while always identifying
        // the admission profile, even if its chosen name has no acronym.
        return $hasAdmissionAcronym ? $name : $name.' · '.self::label();
    }

    public static function visibleText(string $text): string
    {
        return preg_replace(
            '/(?<![\pL\pN])(?:SPMB\s*(?:&|dan|\/)\s*PMB|PMB\s*(?:&|dan|\/)\s*SPMB|SPMB|PMB)(?![\pL\pN])/iu',
            self::label(),
            $text,
        ) ?? $text;
    }

    /**
     * Translate visible HTML text nodes only. Never rewrite HTML attributes,
     * links or markup in configured/legal content.
     */
    public static function visibleHtml(string $html): string
    {
        return preg_replace_callback(
            '/(?<=>)[^<>]+(?=<)/u',
            fn (array $match): string => self::visibleText($match[0]),
            $html,
        ) ?? $html;
    }

    public static function processName(): string
    {
        return match (SpmbOperationalMode::mode()) {
            SpmbOperationalMode::K12 => 'Penerimaan Murid Baru',
            SpmbOperationalMode::HIGHER_EDUCATION => 'Penerimaan Mahasiswa Baru',
            default => 'Penerimaan Murid dan Mahasiswa Baru',
        };
    }

    public static function manifestDescription(): string
    {
        return 'Portal '.self::processName().'.';
    }
}
