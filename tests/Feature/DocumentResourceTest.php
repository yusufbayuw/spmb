<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_are_grouped_by_account_name_without_repeating_registration_identity_columns(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create([
            'name' => 'SMA Taruna Bakti',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'name' => 'Petugas TU',
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('tu');
        $applicant = User::factory()->create(['name' => 'Nama Akun Pendaftar']);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
        ]);
        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registrant_type' => 'self',
            'nik' => '3273010101010088',
            'full_name' => 'Nama Peserta Tidak Ditampilkan',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
        ]);

        foreach (['foto.jpg', 'akta.pdf'] as $fileName) {
            Document::create([
                'registration_id' => $registration->id,
                'type' => $fileName === 'foto.jpg' ? 'photo' : 'birth_certificate',
                'file_path' => 'applicants/'.$registration->id.'/'.$fileName,
                'original_name' => $fileName,
                'file_type' => pathinfo($fileName, PATHINFO_EXTENSION),
                'file_size' => 1024,
            ]);
        }

        $response = $this->actingAs($staff)->get('/admin/documents');

        $response
            ->assertOk()
            ->assertSeeText('Nama Akun Pendaftar')
            ->assertDontSeeText('Nama Peserta Tidak Ditampilkan')
            ->assertDontSeeText((string) $registration->registration_number)
            ->assertSeeText('foto.jpg')
            ->assertSeeText('akta.pdf');
    }
}
