<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationResource as AdminRegistrationResource;
use App\Filament\Applicant\Pages\IdentityPhotoUpload;
use App\Models\Document;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApplicantFileStorage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_can_open_identity_photo_upload_after_registration_number_exists(): void
    {
        [$registration, $user] = $this->fixture();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(IdentityPhotoUpload::class, ['registration' => $registration->uuid])
            ->assertSee('Foto Peserta')
            ->assertSee('Foto Identitas');
    }

    public function test_admin_card_action_is_available_only_when_the_card_is_ready(): void
    {
        [$registration] = $this->fixture();

        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);

        $this->assertFalse(AdminRegistrationResource::canViewApplicantCard($registration));

        $path = 'documents/'.$registration->id.'/admin-card-photo.png';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, 'photo-bytes');

        Document::create([
            'registration_id' => $registration->id,
            'requirement_key' => 'photo',
            'attachment_index' => 0,
            'type' => 'photo',
            'file_path' => $path,
            'original_name' => 'admin-card-photo.png',
            'file_type' => 'png',
            'mime_type' => 'image/png',
            'file_size' => 11,
            'sha256' => hash('sha256', 'photo-bytes'),
            'malware_scan_status' => 'clean',
            'security_scanned_at' => now(),
            'is_verified' => false,
        ]);

        $this->assertTrue(AdminRegistrationResource::canViewApplicantCard($registration->fresh()));

        $registration->update(['lifecycle_status' => 'cancelled']);

        $this->assertFalse(AdminRegistrationResource::canViewApplicantCard($registration->fresh()));
    }

    public function test_card_requires_identity_photo_before_it_can_be_rendered(): void
    {
        [$registration, $user] = $this->fixture();

        $this->actingAs($user)
            ->get(route('registration.card', $registration))
            ->assertStatus(409);
    }

    public function test_card_renders_ktp_template_and_public_verification_without_sensitive_identity_data(): void
    {
        [$registration, $user] = $this->fixture();

        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);
        Storage::fake('public');

        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)
            ->put('documents/'.$registration->id.'/photo.png', 'fake-photo-bytes');

        Storage::disk('public')
            ->put('units/logos/unit.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="navy"/></svg>');

        $registration->unit->update(['logo_path' => 'units/logos/unit.svg']);

        Document::create([
            'registration_id' => $registration->id,
            'requirement_key' => 'photo',
            'attachment_index' => 0,
            'type' => 'photo',
            'file_path' => 'documents/'.$registration->id.'/photo.png',
            'original_name' => 'photo.png',
            'file_type' => 'png',
            'mime_type' => 'image/png',
            'file_size' => 16,
            'sha256' => hash('sha256', 'fake-photo-bytes'),
            'malware_scan_status' => 'clean',
            'security_scanned_at' => now(),
            'is_verified' => false,
        ]);

        $this->actingAs($user)
            ->get(route('registration.card', $registration))
            ->assertOk()
            ->assertSee('Unduh PNG')
            ->assertSee('Unduh JPG')
            ->assertSee('Cetak / Simpan PDF A4')
            ->assertSee('85,6 × 53,98 mm')
            ->assertSee($registration->applicant_card_number)
            ->assertSee($registration->full_name);

        $this->get(route('registration.card.verify', $registration))
            ->assertOk()
            ->assertSee('KARTU VALID')
            ->assertSee($registration->applicant_card_number)
            ->assertSee($registration->full_name)
            ->assertDontSee($registration->nik);
    }

    /**
     * @return array{Registration, User}
     */
    private function fixture(): array
    {
        $unit = Unit::create([
            'name' => 'SMP Taruna Bakti Dengan Nama Unit Panjang',
            'code' => 'SMP',
            'institution_type' => 'school',
            'public_address' => 'Jl. L.L.R.E. Martadinata No. 52 Bandung',
            'is_active' => true,
        ]);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);

        $pathway = RegistrationPathway::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $registration = Registration::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $pathway->id,
            'registration_number' => 'REG-SMP-20262027-0050',
            'applicant_card_number' => 'SMP-0050',
            'applicant_card_issued_at' => now(),
            'full_name' => 'Yariqa Hilmiya Kusumah Dengan Nama Peserta Yang Sangat Panjang',
            'nik' => '3273010403201401',
            'gender' => 'P',
            'birth_place' => 'Bandung',
            'birth_date' => '2014-03-04',
            'home_address' => 'Bandung',
            'previous_school' => 'SD YWKA REL HOMY SCHOOL BANDUNG DENGAN NAMA SANGAT PANJANG',
            'current_stage' => 'documents',
            'lifecycle_status' => 'active',
            'status' => 'verified',
        ]);

        return [$registration, $user];
    }
}
