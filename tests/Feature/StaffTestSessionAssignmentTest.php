<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\AuditLog;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\TestSession;
use App\Models\Unit;
use App\Models\User;
use App\Services\TestBookingService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffTestSessionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_tu_can_assign_session_after_applicant_booking_deadline_with_audit_and_notification(): void
    {
        [$registration, $parent, $staff, $test] = $this->fixture();

        $session = TestSession::create([
            'admission_test_id' => $test->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'booking_closes_at' => now()->subHour(),
            'location' => 'Lab Komputer',
            'capacity' => 10,
            'status' => 'active',
        ]);

        $result = $registration->testResults()->where('admission_test_id', $test->id)->firstOrFail();

        $booking = app(TestBookingService::class)->assignByStaff(
            $result,
            $session,
            $staff,
            'Pendaftar lupa memilih sesi tes.',
        );

        $this->assertSame($session->id, $booking->test_session_id);
        $this->assertSame(1, $booking->revision);
        $this->assertSame('scheduled', $result->fresh()->status);

        $audit = AuditLog::query()->where('event', 'test.booking.admin_assigned')->latest('id')->firstOrFail();
        $this->assertSame($staff->id, $audit->user_id);
        $this->assertSame($registration->id, $audit->registration_id);
        $this->assertTrue((bool) data_get($audit->metadata, 'deadline_override'));
        $this->assertSame('Pendaftar lupa memilih sesi tes.', data_get($audit->metadata, 'reason'));

        $this->assertTrue(
            $parent->notifications()->where('data->title', 'Jadwal tes ditetapkan panitia')->exists(),
        );
    }

    public function test_staff_cannot_assign_full_or_started_session(): void
    {
        [$registration, $parent, $staff, $test] = $this->fixture();

        $full = TestSession::create([
            'admission_test_id' => $test->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'booking_closes_at' => now()->addHour(),
            'location' => 'Ruang Penuh',
            'capacity' => 1,
            'status' => 'active',
        ]);

        $otherRegistration = $registration->replicate();
        $otherRegistration->nik = '3273010101010002';
        $otherRegistration->save();
        $otherResult = AdmissionTestResult::create([
            'registration_id' => $otherRegistration->id,
            'admission_test_id' => $test->id,
            'status' => 'unbooked',
            'result' => 'pending',
        ]);
        app(TestBookingService::class)->assignByStaff($otherResult, $full, $staff, 'Penjadwalan panitia.');

        $result = $registration->testResults()->where('admission_test_id', $test->id)->firstOrFail();

        try {
            app(TestBookingService::class)->assignByStaff($result, $full, $staff, 'Pendaftar lupa memilih.');
            $this->fail('Sesi penuh seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('session', $exception->errors());
        }

        $started = TestSession::create([
            'admission_test_id' => $test->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'booking_closes_at' => now()->subDay(),
            'location' => 'Ruang Lama',
            'capacity' => 10,
            'status' => 'active',
        ]);

        $this->expectException(ValidationException::class);
        app(TestBookingService::class)->assignByStaff($result, $started, $staff, 'Koreksi jadwal.');
    }

    public function test_print_layout_uses_a4_with_safe_margin(): void
    {
        $layout = file_get_contents(resource_path('views/registration/print-layout.blade.php'));

        $this->assertStringContainsString('@page{size:A4 portrait;margin:15mm}', $layout);
        $this->assertStringContainsString('min-height:267mm', $layout);
        $this->assertStringContainsString('break-inside:avoid', $layout);
    }

    private function fixture(): array
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'Unit Test',
            'code' => 'SMA',
            'is_active' => true,
        ]);

        $parent = User::factory()->create(['is_active' => true]);
        $parent->assignRole('pendaftar');

        $staff = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('tu');

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
        ]);

        $registration = Registration::create([
            'user_id' => $parent->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'full_name' => 'Peserta Test',
            'nik' => '3273010101010001',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
            'current_stage' => 'tests',
        ]);

        $test = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akademik',
            'is_required' => true,
            'is_active' => true,
        ]);

        AdmissionTestResult::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $test->id,
            'status' => 'unbooked',
            'result' => 'pending',
        ]);

        return [$registration, $parent, $staff, $test];
    }
}
