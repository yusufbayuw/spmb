<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\ParentInfo;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\RegistrationAcademicScore;
use App\Models\RegistrationAchievement;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Models\Village;
use App\Services\UnitConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrintableRegistrationFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_print_pinned_data_parent_information_scores_and_achievements(): void
    {
        [$registration, $staff] = $this->fixture();

        ParentInfo::create([
            'registration_id' => $registration->id,
            'father_name' => 'Ayah Contoh',
            'mother_name' => 'Ibu Contoh',
            'guardian_phone' => '081234567890',
        ]);

        RegistrationAcademicScore::create([
            'registration_id' => $registration->id,
            'grade_key' => 'vii', 'subject_key' => 'math', 'assessment_key' => 's1', 'score' => 93.5,
        ]);

        RegistrationAchievement::create([
            'registration_id' => $registration->id, 'title' => 'Olimpiade Fisika',
            'level' => 'Nasional', 'year' => 2026,
        ]);

        $response = $this->actingAs($staff)->get(route('registration.printable-form', $registration));

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertSee('Formulir Pendaftaran')
            ->assertSee('Ayah Contoh')
            ->assertSee('Ibu Contoh')
            ->assertSee('Matematika')
            ->assertSee('Kelas VII')
            ->assertSee('Semester 1')
            ->assertSee('93,50')
            ->assertSee('Olimpiade Fisika')
            ->assertSee('Jawaban Khusus')
            ->assertDontSee('Jawaban Rahasia')
            ->assertDontSee('subject_key')
            ->assertDontSee('certificate_path');

        $cacheControl = $response->headers->get('Cache-Control', '');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }

    public function test_printable_form_shows_saved_region_names_instead_of_codes_without_changing_data(): void
    {
        [$registration, $staff] = $this->fixture();

        $registration->forceFill([
            'province_code' => '32',
            'city_code' => '32.73',
            'district_code' => '32.73.05',
            'village_code' => '32.73.05.1002',
            'province' => 'Jawa Barat',
            'city' => 'Kota Bandung',
            'district' => 'Bandung Wetan',
            'village' => 'Cihapit',
        ])->saveQuietly();

        $this->actingAs($staff)
            ->get(route('registration.printable-form', $registration))
            ->assertOk()
            ->assertSee('>Jawa Barat</td>', false)
            ->assertSee('>Kota Bandung</td>', false)
            ->assertSee('>Bandung Wetan</td>', false)
            ->assertSee('>Cihapit</td>', false)
            ->assertDontSee('>32</td>', false)
            ->assertDontSee('>32.73</td>', false)
            ->assertDontSee('>32.73.05</td>', false)
            ->assertDontSee('>32.73.05.1002</td>', false);

        $this->assertSame('32', $registration->fresh()->province_code);
        $this->assertSame('32.73.05.1002', $registration->fresh()->village_code);
        $this->assertSame('Jawa Barat', $registration->fresh()->province);
    }

    public function test_printable_form_resolves_region_names_from_master_for_code_only_records(): void
    {
        [$registration, $staff] = $this->fixture();

        Province::create(['code' => '32', 'name' => 'Jawa Barat']);
        Regency::create(['code' => '32.73', 'province_code' => '32', 'name' => 'Kota Bandung']);
        District::create(['code' => '32.73.05', 'regency_code' => '32.73', 'name' => 'Bandung Wetan']);
        Village::create(['code' => '32.73.05.1002', 'district_code' => '32.73.05', 'name' => 'Cihapit']);

        // Legacy registration has only region codes; printing must not update it.
        $registration->forceFill([
            'province_code' => '32',
            'city_code' => '32.73',
            'district_code' => '32.73.05',
            'village_code' => '32.73.05.1002',
        ])->saveQuietly();

        $this->actingAs($staff)
            ->get(route('registration.printable-form', $registration))
            ->assertOk()
            ->assertSee('>Jawa Barat</td>', false)
            ->assertSee('>Kota Bandung</td>', false)
            ->assertSee('>Bandung Wetan</td>', false)
            ->assertSee('>Cihapit</td>', false)
            ->assertDontSee('>32.73.05.1002</td>', false)
            ->assertSee('Peserta Cetak');

        $this->assertNull($registration->fresh()->province);
        $this->assertNull($registration->fresh()->village);
        $this->assertSame('32.73.05.1002', $registration->fresh()->village_code);
    }

    public function test_applicant_and_other_unit_staff_cannot_open_printable_form(): void
    {
        [$registration, $staff, $applicant] = $this->fixture();
        $otherUnit = Unit::create([
            'name' => 'Unit Lain', 'code' => 'PRINT-B', 'institution_type' => 'school', 'is_active' => true,
        ]);
        $staff->update(['unit_id' => $otherUnit->id]);

        $this->actingAs($staff)
            ->get(route('registration.printable-form', $registration))
            ->assertNotFound();

        $this->actingAs($applicant)
            ->get(route('registration.printable-form', $registration))
            ->assertNotFound();

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id, 'current_stage' => 'data_validation',
        ]);
    }

    public function test_super_admin_can_print_across_units_without_modifying_registration(): void
    {
        [$registration] = $this->fixture();
        Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('registration.printable-form', $registration))
            ->assertOk()
            ->assertSee($registration->full_name);

        $this->assertSame('data_validation', $registration->fresh()->current_stage);
    }

    /** @return array{Registration, User, User} */
    private function fixture(): array
    {
        $unit = Unit::create([
            'name' => 'Unit Cetak', 'code' => 'PRINT-A', 'institution_type' => 'school', 'is_active' => true,
        ]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1',
            'status' => 'open', 'registration_fee' => 0,
        ]);
        $pathway = RegistrationPathway::factory()->create([
            'unit_id' => $unit->id, 'name' => 'Reguler', 'is_active' => true,
        ]);
        $applicant = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        Role::findOrCreate('tu', 'web');
        $staff = User::factory()->create([
            'unit_id' => $unit->id, 'role' => 'tu',
            'is_active' => true, 'email_verified_at' => now(),
        ]);
        $staff->assignRole('tu');

        $configuration = UnitConfiguration::create(array_merge(
            app(UnitConfigurationService::class)->defaults($unit),
            [
                'unit_id' => $unit->id,
                'version' => 5, 'status' => 'published', 'legacy' => false, 'published_at' => now(),
                'fields' => [
                    ['key' => 'custom_question', 'label' => 'Pertanyaan Khusus', 'type' => 'text', 'active' => true, 'group_key' => 'detail'],
                    ['key' => 'hidden_question', 'label' => 'Pertanyaan Nonaktif', 'type' => 'text', 'active' => false, 'group_key' => 'detail'],
                ],
                'form_groups' => [['key' => 'detail', 'label' => 'Informasi Tambahan']],
                'form_layout' => ['registration_choice', 'identity', 'parents', 'group:detail', 'academic_scores', 'achievements'],
                'academic_scores_enabled' => true,
                'academic_score_settings' => [
                    'grades' => [['key' => 'vii', 'label' => 'Kelas VII']],
                    'subjects' => [['key' => 'math', 'label' => 'Matematika']],
                    'assessments' => [['key' => 's1', 'label' => 'Semester 1']],
                ],
                'achievements_enabled' => true,
            ],
        ));

        $registration = Registration::create([
            'user_id' => $applicant->id, 'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $pathway->id,
            'unit_configuration_id' => $configuration->id,
            'registration_number' => 'REG-PRINT-0001', 'full_name' => 'Peserta Cetak',
            'nik' => '3273010403201401', 'gender' => 'P', 'birth_place' => 'Bandung',
            'birth_date' => '2014-03-04', 'home_address' => 'Bandung',
            'current_stage' => 'data_validation', 'lifecycle_status' => 'active',
            'status' => 'submitted',
            'custom_answers' => ['custom_question' => 'Jawaban Khusus', 'hidden_question' => 'Jawaban Rahasia'],
        ]);

        return [$registration, $staff, $applicant];
    }
}
