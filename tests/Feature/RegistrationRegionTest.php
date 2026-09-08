<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Services\IndonesiaRegionImportService;
use App\Services\RegistrationRegionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrationRegionTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_import_upserts_complete_hierarchy(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'regions-');
        file_put_contents($path, implode("\n", [
            'province_code,province_name,regency_code,regency_name,district_code,district_name,village_code,village_name',
            '32,Jawa Barat,32.73,Kota Bandung,32.73.05,Bandung Wetan,32.73.05.1002,Cihapit',
        ]));

        try {
            $result = app(IndonesiaRegionImportService::class)->import($path, 100);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['rows']);
        $this->assertDatabaseHas('provinces', ['code' => '32', 'name' => 'Jawa Barat']);
        $this->assertDatabaseHas('regencies', ['code' => '32.73', 'province_code' => '32', 'name' => 'Kota Bandung']);
        $this->assertDatabaseHas('districts', ['code' => '32.73.05', 'regency_code' => '32.73', 'name' => 'Bandung Wetan']);
        $this->assertDatabaseHas('villages', ['code' => '32.73.05.1002', 'district_code' => '32.73.05', 'name' => 'Cihapit']);
    }

    public function test_region_normalization_uses_master_names_and_rejects_invalid_hierarchy(): void
    {
        $this->regionFixture();

        $normalized = app(RegistrationRegionService::class)->normalize([
            'province_code' => '32',
            'city_code' => '32.73',
            'district_code' => '32.73.05',
            'village_code' => '32.73.05.1002',
        ]);

        $this->assertSame('Jawa Barat', $normalized['province']);
        $this->assertSame('Kota Bandung', $normalized['city']);
        $this->assertSame('Bandung Wetan', $normalized['district']);
        $this->assertSame('Cihapit', $normalized['village']);

        Province::create(['code' => '31', 'name' => 'DKI Jakarta']);
        Regency::create(['code' => '31.71', 'province_code' => '31', 'name' => 'Kota Jakarta Pusat']);

        $this->expectException(ValidationException::class);

        app(RegistrationRegionService::class)->normalize([
            'province_code' => '32',
            'city_code' => '31.71',
        ]);
    }

    private function regionFixture(): void
    {
        Province::create(['code' => '32', 'name' => 'Jawa Barat']);
        Regency::create(['code' => '32.73', 'province_code' => '32', 'name' => 'Kota Bandung']);
        District::create(['code' => '32.73.05', 'regency_code' => '32.73', 'name' => 'Bandung Wetan']);
        Village::create(['code' => '32.73.05.1002', 'district_code' => '32.73.05', 'name' => 'Cihapit']);
    }
}
