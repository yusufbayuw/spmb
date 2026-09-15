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
        $regencyCode = '32'.'73';
        $districtCode = $regencyCode.'05';
        $villageCode = $districtCode.'1002';
        $row = implode(',', [
            '32',
            'Jawa Barat',
            $regencyCode,
            'Kota Bandung',
            $districtCode,
            'Bandung Wetan',
            $villageCode,
            'Cihapit',
        ]);

        file_put_contents($path, implode("\n", [
            'province_code,province_name,regency_code,regency_name,district_code,district_name,village_code,village_name',
            $row,
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

        $component = Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->assertSee('Provinsi')
            ->assertSee('Kabupaten/Kota')
            ->assertSee('Kecamatan')
            ->assertSee('Desa/Kelurahan')
            ->assertSee('Kirim Pendaftaran')
            ->assertDontSee('Buat & buat lainnya');

        $component
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

        $this->get('/pendaftar/status/'.$registration->uuid)
            ->assertOk()
            ->assertSeeText('Cihapit, Bandung Wetan, Kota Bandung, Jawa Barat');
    }

    public function test_bundled_region_dataset_covers_all_indonesia(): void
    {
        $files = glob(database_path('data/indonesia_regions/*.csv')) ?: [];

        $this->assertCount(38, $files);

        $provinces = [];
        $regencies = [];
        $districts = [];
        $villages = [];
        $kelurahan = 0;
        $desa = 0;

        foreach ($files as $path) {
            $file = new \SplFileObject($path, 'r');
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

            $this->assertSame([
                'province_code',
                'province_name',
                'regency_code',
                'regency_name',
                'district_code',
                'district_name',
                'village_code',
                'village_name',
            ], $file->fgetcsv());

            while (! $file->eof()) {
                $row = $file->fgetcsv();

                if ($row === false || $row === [null]) {
                    continue;
                }

                $this->assertCount(8, $row);

                [$provinceCode, , $regencyCode, , $districtCode, , $villageCode] = $row;

                $this->assertStringStartsWith($provinceCode.'.', $regencyCode);
                $this->assertStringStartsWith($regencyCode.'.', $districtCode);
                $this->assertStringStartsWith($districtCode.'.', $villageCode);

                $provinces[$provinceCode] = true;
                $regencies[$regencyCode] = true;
                $districts[$districtCode] = true;
                $villages[$villageCode] = true;

                $localCode = explode('.', $villageCode)[3] ?? '';

                if (str_starts_with($localCode, '1')) {
                    $kelurahan++;
                } elseif (str_starts_with($localCode, '2') || str_starts_with($localCode, '3')) {
                    $desa++;
                }
            }
        }

        $this->assertCount(38, $provinces);
        $this->assertCount(514, $regencies);
        $this->assertCount(7285, $districts);
        $this->assertCount(83762, $villages);
        $this->assertSame(8496, $kelurahan);
        $this->assertSame(75266, $desa);
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
