<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApplicantFileStorage;
use App\Services\ControlledDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlledDeletionFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_delete_removes_private_attachments_after_database_commit(): void
    {
        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);
        Storage::fake(ApplicantFileStorage::LEGACY_PUBLIC_DISK);

        $unit = Unit::create([
            'name' => 'File Delete', 'code' => 'FILEDEL',
            'is_active' => true, 'allow_admin_unit_registration_deletion' => true,
        ]);
        Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);
        $staff = User::factory()->create(['unit_id' => $unit->id, 'role' => 'admin_unit', 'is_active' => true]);
        $staff->assignRole('admin_unit');
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2026/2027',
            'wave' => 'G1', 'registration_fee' => 0, 'status' => 'draft',
        ]);
        $registration = Registration::create([
            'user_id' => User::factory()->create()->id,
            'unit_id' => $unit->id, 'registration_opening_id' => $opening->id,
            'nik' => '3273010101010007', 'full_name' => 'File Subject',
            'gender' => 'L', 'birth_place' => 'Bandung',
            'birth_date' => '2018-01-01', 'home_address' => 'Bandung',
        ]);
        $path = 'documents/'.$registration->id.'/test.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, '%PDF');
        Document::create([
            'registration_id' => $registration->id,
            'type' => 'supporting_document', 'file_path' => $path,
            'original_name' => 'test.pdf', 'file_type' => 'pdf', 'file_size' => 4,
        ]);

        $result = app(ControlledDeletionService::class)->deleteRegistration(
            $registration, $staff, 'Data duplikat', 'HAPUS'
        );

        $this->assertSame(1, $result['file_count']);
        $this->assertSame(0, $result['file_errors']);
        $this->assertDatabaseMissing('documents', ['registration_id' => $registration->id]);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertMissing($path);
    }
}
