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

    public function test_documents_are_grouped_by_registration_identity_instead_of_account_name(): void
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
            ->assertSeeText(($registration->registration_number ?: 'Tanpa nomor registrasi').' · Nama Peserta Tidak Ditampilkan')
            ->assertDontSeeText('Nama Akun Pendaftar')
            ->assertSeeText('pas_foto_tanpa_nomor_registrasi.jpg')
            ->assertSeeText('akta_kelahiran_tanpa_nomor_registrasi.pdf');
    }

    public function test_document_display_name_uses_requirement_and_registration_number(): void
    {
        $unit = Unit::create([
            'name' => 'SMA Taruna Bakti',
            'code' => 'SMA-NAME',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $applicant = User::factory()->create(['is_active' => true]);
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
            'registration_number' => 'REG-SMA-20262027-0001',
            'registrant_type' => 'self',
            'nik' => '3273010101010099',
            'full_name' => 'Jajang Miharjang',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
        ]);
        $document = Document::create([
            'registration_id' => $registration->id,
            'type' => 'report_card',
            'file_path' => 'applicants/'.$registration->id.'/upload-asli.pdf',
            'original_name' => 'Absen Siswa X Dapodik.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
        ]);

        $this->assertSame('rapor_REG-SMA-20262027-0001.pdf', $document->displayFileName());
    }
}
