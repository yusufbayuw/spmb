<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationResource as AdminRegistrationResource;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApplicantFileStorage;
use App\Services\ApplicantUploadSecurity;
use App\Services\OperationalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);
        config([
            'spmb.uploads.require_malware_scan' => false,
            'spmb.uploads.clamav_binary' => 'definitely-not-installed-clamscan',
        ]);
    }

    public function test_upload_security_accepts_real_pdf_and_records_integrity_metadata(): void
    {
        $path = 'documents/1/rapor.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

        $result = app(ApplicantUploadSecurity::class)->inspect($path);

        $this->assertSame('application/pdf', $result['mime_type']);
        $this->assertSame(64, strlen($result['sha256']));
        $this->assertSame('unavailable', $result['malware_scan_status']);
        $this->assertNotNull($result['security_scanned_at']);
    }

    public function test_upload_security_rejects_file_disguised_as_pdf(): void
    {
        $path = 'documents/1/evil.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, '<?php echo "pwned";');

        $this->expectException(ValidationException::class);
        app(ApplicantUploadSecurity::class)->inspect($path);
    }

    public function test_required_antivirus_fails_closed_when_scanner_is_unavailable(): void
    {
        config(['spmb.uploads.require_malware_scan' => true]);
        $path = 'documents/1/rapor.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($path, "%PDF-1.4\n%%EOF");

        $this->expectException(ValidationException::class);
        app(ApplicantUploadSecurity::class)->inspect($path);
    }

    public function test_hard_delete_is_disabled_and_registration_uses_lifecycle(): void
    {
        [$registration, $staff] = $this->registrationFixture('SMA', '3273010101010001');

        $this->assertFalse(AdminRegistrationResource::canDelete($registration));

        $registration->changeLifecycle('cancelled', $staff, 'Data duplikat');
        $this->assertSame('cancelled', $registration->fresh()->lifecycle_status);
        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);

        $registration->fresh()->changeLifecycle('active', $staff);
        $this->assertSame('active', $registration->fresh()->lifecycle_status);
    }

    public function test_tu_report_is_always_scoped_to_own_unit_and_export_is_downloadable(): void
    {
        [$registrationA, $tu, $unitA] = $this->registrationFixture('SMA', '3273010101010002', role: 'tu');
        [$registrationB, , $unitB] = $this->registrationFixture('SMP', '3273010101010003');

        Payment::create([
            'registration_id' => $registrationA->id,
            'amount' => 350000,
            'status' => 'verified',
        ]);
        Payment::create([
            'registration_id' => $registrationB->id,
            'amount' => 999999,
            'status' => 'verified',
        ]);

        $summary = app(OperationalReportService::class)->summary($tu, ['unit_id' => $unitB->id]);

        $this->assertSame(1, $summary['total']);
        $this->assertSame(350000.0, $summary['expected_fee']);
        $this->assertSame(350000.0, $summary['verified_revenue']);

        $this->actingAs($tu)
            ->get(route('reports.operational.xlsx', ['unit_id' => $unitB->id]))
            ->assertOk()
            ->assertDownload();
    }

    private function registrationFixture(string $code, string $nik, string $role = 'staff'): array
    {
        $unit = Unit::create([
            'name' => $code.' Taruna Bakti',
            'code' => $code,
            'is_active' => true,
        ]);

        $applicant = User::factory()->create(['is_active' => true]);
        $staff = User::factory()->create(['is_active' => true, 'unit_id' => $unit->id]);

        if ($role === 'tu') {
            $tuRole = Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
            $staff->assignRole($tuRole);
        }

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'pathway' => 'Reguler',
            'registration_fee' => 350000,
            'status' => 'open',
        ]);

        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => $nik,
            'full_name' => 'Calon '.$code,
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2015-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
        ]);

        return [$registration, $staff, $unit, $opening];
    }
}
