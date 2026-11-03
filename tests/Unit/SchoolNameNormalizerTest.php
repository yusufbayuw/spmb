<?php

namespace Tests\Unit;

use App\Services\SchoolNameNormalizer;
use Tests\TestCase;

class SchoolNameNormalizerTest extends TestCase
{
    public function test_normalizes_whitespace_case_punctuation_and_common_abbreviations(): void
    {
        $normalizer = app(SchoolNameNormalizer::class);

        $this->assertSame('SMP TARUNA BAKTI', $normalizer->normalize("  smp   Taruna \t Bakti  "));
        $this->assertSame('SMP TARUNA BAKTI', $normalizer->normalize('Taruna Bakti Bandung'));
        $this->assertSame('SMP NEGERI 2 BANDUNG', $normalizer->normalize(" \tSmPn   2\u{00A0}BanDung \n"));
        $this->assertSame('SMP NEGERI 2 BANDUNG', $normalizer->normalize('SMP N 2 BANDUNG'));
        $this->assertSame('SMP ISLAM AL AZHAR 36 BANDUNG', $normalizer->normalize('Al -azhar 36 bandung'));
        $this->assertSame('SMP MUHAMMADIYAH 8 BANDUNG', $normalizer->normalize('SMP Muhamadiyah 8 Bandung'));
        $this->assertSame('SMP INOVATIF AS SYIFA', $normalizer->normalize("SMP Inovatif As-Syifa"));
    }

    public function test_missing_schools_are_countable_and_ambiguous_names_are_not_guessed(): void
    {
        $normalizer = app(SchoolNameNormalizer::class);

        $this->assertSame('TIDAK DIISI', $normalizer->normalize(null));
        $this->assertSame('TIDAK DIISI', $normalizer->normalize(" \t\u{00A0}\n"));
        $this->assertSame('TIDAK DIISI', $normalizer->normalize(' - '));
        $this->assertSame('SMP NEGERI 2', $normalizer->normalize('SMPN2'));
        $this->assertNotSame(
            $normalizer->normalize('SMP Darul Hikam'),
            $normalizer->normalize('SMP Darul Hikam Integrated School'),
        );
    }
}
