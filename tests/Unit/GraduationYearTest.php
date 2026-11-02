<?php

namespace Tests\Unit;

use App\Rules\GraduationYear;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GraduationYearTest extends TestCase
{
    public function test_valid_year_values(): void
    {
        foreach (['2000', '2026', 2026, (string) (now()->year + 2), null, ''] as $value) {
            $validator = Validator::make(['graduation_year' => $value], [
                'graduation_year' => ['nullable', new GraduationYear()],
            ]);
            $this->assertTrue($validator->passes(), 'Expected year to pass: '.var_export($value, true));
        }
    }

    public function test_invalid_year_values_are_rejected(): void
    {
        foreach (['2026/2027', '2026-2027', '202A', '1999', '202', '02026', '2026 ', '9999', [], true] as $value) {
            $validator = Validator::make(['graduation_year' => $value], [
                'graduation_year' => ['nullable', new GraduationYear()],
            ]);
            $this->assertTrue($validator->fails(), 'Expected year to fail: '.var_export($value, true));
            $this->assertArrayHasKey('graduation_year', $validator->errors()->toArray());
        }
    }
}
