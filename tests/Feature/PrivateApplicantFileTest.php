<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApplicantFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrivateApplicantFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);
        Storage::fake(ApplicantFileStorage::LEGACY_PUBLIC_DISK);
    }

    public function test_applicant_file_routes_require_authentication(): void
    {
        [$owner, $registration] = $this->registrationForApplicant();

        $document = Document::create([
            'registration_id' => $registration->id,
            'type' => 'family_card',
            'file_path' => 'documents/'.$registration->id.'/kk.pdf',
            'original_name' => 'kk.pdf',
            'file_type' => 'pdf',
        ]);

        $this->get(route('files.applicant.documents.show', $document))
            ->assertRedirect();
    }

    public function test_applicant_can_preview_own_document_and_other_applicant_is_forbidden(): void
    {
        [$owner, $registration] = $this->registrationForApplicant();
        $other = $this->applicant();

        $path = 'documents/'.$registration->id.'/rapor.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, '%PDF-1.4 private');

        $document = Document::create([
            'registration_id' => $registration->id,
            'type' => 'report_card',
            'file_path' => $path,
            'original_name' => 'rapor.pdf',
            'file_type' => 'pdf',
        ]);

        $response = $this->actingAs($owner)
            ->get(route('files.applicant.documents.show', $document))
            ->assertOk();

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );

        $this->actingAs($other)
            ->get(route('files.applicant.documents.show', $document))
            ->assertForbidden();
    }

    public function test_legacy_public_document_is_moved_to_private_storage_on_authorized_access(): void
    {
        [$owner, $registration] = $this->registrationForApplicant();

        $path = 'documents/'.$registration->id.'/akta.pdf';
        Storage::disk(ApplicantFileStorage::LEGACY_PUBLIC_DISK)->put($path, '%PDF-1.4 legacy');

        $document = Document::create([
            'registration_id' => $registration->id,
            'type' => 'birth_certificate',
            'file_path' => $path,
            'original_name' => 'akta.pdf',
            'file_type' => 'pdf',
        ]);

        $this->actingAs($owner)
            ->get(route('files.applicant.documents.show', $document))
            ->assertOk();

        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertExists($path);
        Storage::disk(ApplicantFileStorage::LEGACY_PUBLIC_DISK)->assertMissing($path);
    }

    public function test_payment_proof_is_owner_only_and_download_uses_authenticated_route(): void
    {
        [$owner, $registration] = $this->registrationForApplicant();
        $other = $this->applicant();

        $path = 'payments/'.$registration->id.'/proof.png';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, 'png-content');

        $payment = Payment::create([
            'registration_id' => $registration->id,
            'amount' => 100000,
            'status' => 'paid',
            'proof_path' => $path,
            'proof_original_name' => 'bukti.png',
        ]);

        $this->actingAs($owner)
            ->get(route('files.applicant.payments.proof', ['payment' => $payment, 'download' => 1]))
            ->assertOk()
            ->assertDownload('bukti.png');

        $this->actingAs($other)
            ->get(route('files.applicant.payments.proof', $payment))
            ->assertForbidden();
    }

    public function test_tu_can_access_only_files_from_its_own_unit_with_permission(): void
    {
        $unitA = Unit::create(['name' => 'SMA', 'code' => 'SMA', 'is_active' => true]);
        $unitB = Unit::create(['name' => 'SMP', 'code' => 'SMP', 'is_active' => true]);

        $tuRole = Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'view_document', 'guard_name' => 'web']);
        $tuRole->givePermissionTo($permission);

        $tu = User::factory()->create([
            'unit_id' => $unitA->id,
            'is_active' => true,
        ]);
        $tu->assignRole($tuRole);

        $owner = $this->applicant();

        $ownUnitRegistration = $this->registration($owner, $unitA, '1111111111111111');
        $otherUnitRegistration = $this->registration($owner, $unitB, '2222222222222222');

        $ownDocument = $this->documentWithPrivateFile($ownUnitRegistration, 'own.pdf');
        $otherDocument = $this->documentWithPrivateFile($otherUnitRegistration, 'other.pdf');

        $this->actingAs($tu)
            ->get(route('files.applicant.documents.show', $ownDocument))
            ->assertOk();

        $this->actingAs($tu)
            ->get(route('files.applicant.documents.show', $otherDocument))
            ->assertForbidden();
    }

    private function registrationForApplicant(): array
    {
        $owner = $this->applicant();
        $unit = Unit::create([
            'name' => 'SD '.uniqid(),
            'code' => 'SD'.random_int(100, 999),
            'is_active' => true,
        ]);

        return [$owner, $this->registration($owner, $unit, (string) random_int(1000000000000000, 9999999999999999))];
    }

    private function registration(User $owner, Unit $unit, string $nik): Registration
    {
        return Registration::create([
            'user_id' => $owner->id,
            'unit_id' => $unit->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => $nik,
            'full_name' => 'Calon Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2019-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'documents',
        ]);
    }

    private function documentWithPrivateFile(Registration $registration, string $name): Document
    {
        $path = 'documents/'.$registration->id.'/'.$name;
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, '%PDF-1.4');

        return Document::create([
            'registration_id' => $registration->id,
            'type' => 'supporting_document',
            'file_path' => $path,
            'original_name' => $name,
            'file_type' => 'pdf',
        ]);
    }

    private function applicant(): User
    {
        $role = Role::firstOrCreate([
            'name' => 'pendaftar',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
