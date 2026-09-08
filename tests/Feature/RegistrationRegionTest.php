<?php

namespace Tests\Feature;

use App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Models\Village;
use App\Services\IndonesiaRegionImportService;
use App\Services\RegistrationRegionService;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
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

    public function test_applicant_region_fields_save_codes_and_canonical_names(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->regionFixture();

        $unit = Unit::create([
            'name' => 'SMA Wilayah',
            'code' => 'SMA-WIL',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('tu');
        $parent = User::factory()->create(['is_active' => true]);
        $parent->assignRole('pendaftar');
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);
        $pathway = RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);

        $service = app(UnitConfigurationService::class);
        $service->initialize($unit);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['fields'] = [
            $this->regionDefinition('province_code', 'Provinsi'),
            $this->regionDefinition('city_code', 'Kabupaten/Kota'),
            $this->regionDefinition('district_code', 'Kecamatan'),
            $this->regionDefinition('village_code', 'Desa/Kelurahan'),
        ];
        $service->save($draft, $staff, $data, true);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        $nik = str_repeat('9', 15).'8';

        Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->fillForm([
                'registration_pathway_uuid' => $pathway->uuid,
                'registrant_type' => 'self',
                'full_name' => 'Peserta Wilayah',
                'nik' => $nik,
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2010-01-01',
                'home_address' => 'Jl. Cihapit',
                'province_code' => '32',
                'city_code' => '32.73',
                'district_code' => '32.73.05',
                'village_code' => '32.73.05.1002',
                'parentInfo' => [
                    'father_name' => 'Ayah Peserta',
                    'mother_name' => 'Ibu Peserta',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $registration = Registration::query()->where('nik', $nik)->firstOrFail();

        $this->assertSame('32', $registration->province_code);
        $this->assertSame('32.73', $registration->city_code);
        $this->assertSame('32.73.05', $registration->district_code);
        $this->assertSame('32.73.05.1002', $registration->village_code);
        $this->assertSame('Jawa Barat', $registration->province);
        $this->assertSame('Kota Bandung', $registration->city);
        $this->assertSame('Bandung Wetan', $registration->district);
        $this->assertSame('Cihapit', $registration->village);
    }

    /**
     * @return array<string, mixed>
     */
    private function regionDefinition(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'active' => true,
            'required' => true,
            'group' => 'Alamat',
            'help' => null,
            'options' => [],
        ];
    }

    private function regionFixture(): void
    {
        Province::create(['code' => '32', 'name' => 'Jawa Barat']);
        Regency::create(['code' => '32.73', 'province_code' => '32', 'name' => 'Kota Bandung']);
        District::create(['code' => '32.73.05', 'regency_code' => '32.73', 'name' => 'Bandung Wetan']);
        Village::create(['code' => '32.73.05.1002', 'district_code' => '32.73.05', 'name' => 'Cihapit']);
    }
}
