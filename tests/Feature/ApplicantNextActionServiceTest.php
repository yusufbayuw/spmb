<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\TestBooking;
use App\Models\TestSession;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApplicantNextActionService;
use App\Services\TestScheduleConfirmationService;
use App\Services\UnitConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantNextActionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prioritizes_applicant_actions_and_combines_multiple_registrations(): void
    {
        [$first, $second] = $this->registrations();

        $first->update([
            'current_stage' => 'selection',
            'data_validation_status' => 'valid',
        ]);

        $second->update([
            'current_stage' => 'payment',
            'data_validation_status' => 'valid',
        ]);

        $items = app(ApplicantNextActionService::class)->resolveMany([$first->fresh(), $second->fresh()]);

        $this->assertCount(2, $items);
        $this->assertSame($second->uuid, $items[0]['registration_uuid']);
        $this->assertSame('action_required', $items[0]['state']);
        $this->assertSame('Upload Bukti Pembayaran', $items[0]['action_label']);
        $this->assertSame($first->uuid, $items[1]['registration_uuid']);
        $this->assertSame('processing', $items[1]['state']);
    }

    public function test_revision_overrides_the_stage_waiting_state(): void
    {
        [$registration] = $this->registrations();

        $registration->update([
            'current_stage' => 'data_validation',
            'data_validation_status' => 'revision',
            'data_validation_notes' => 'NIK perlu diperbaiki.',
        ]);

        $item = app(ApplicantNextActionService::class)->resolve($registration->fresh());

        $this->assertNotNull($item);
        $this->assertSame('attention', $item['state']);
        $this->assertSame('warning', $item['color']);
        $this->assertSame('Perbaiki Data', $item['action_label']);
        $this->assertSame('NIK perlu diperbaiki.', $item['message']);
        $this->assertNotNull($item['action_url']);
    }

    public function test_test_stage_action_tracks_required_schedule_completion_and_confirmation(): void
    {
        [$registration] = $this->registrations();
        $unit = $registration->unit()->firstOrFail();

        $firstTest = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akademik',
            'code' => 'AKAD-ACTION',
            'is_required' => true,
            'is_active' => true,
        ]);
        $secondTest = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Wawancara',
            'code' => 'WAW-ACTION',
            'is_required' => true,
            'is_active' => true,
        ]);

        app(UnitConfigurationService::class)->initialize($unit);

        $registration->refresh()->update([
            'current_stage' => 'tests',
            'data_validation_status' => 'valid',
        ]);

        $service = app(ApplicantNextActionService::class);
        $item = $service->resolve($registration->fresh());

        $this->assertSame('Lengkapi Jadwal Tes', $item['title']);
        $this->assertSame('0 dari 2 tes wajib sudah dipilih. Masih ada 2 tes wajib yang belum memiliki jadwal.', $item['message']);

        $firstSession = TestSession::create([
            'admission_test_id' => $firstTest->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'booking_closes_at' => now()->addDays(2),
            'location' => 'Ruang Akademik',
            'capacity' => 20,
            'status' => 'active',
        ]);
        $secondSession = TestSession::create([
            'admission_test_id' => $secondTest->id,
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHour(),
            'booking_closes_at' => now()->addDays(3),
            'location' => 'Ruang Wawancara',
            'capacity' => 20,
            'status' => 'active',
        ]);

        TestBooking::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $firstTest->id,
            'test_session_id' => $firstSession->id,
            'revision' => 1,
        ]);

        $item = $service->resolve($registration->fresh());

        $this->assertSame('1 dari 2 tes wajib sudah dipilih. Masih ada 1 tes wajib yang belum memiliki jadwal.', $item['message']);

        TestBooking::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $secondTest->id,
            'test_session_id' => $secondSession->id,
            'revision' => 1,
        ]);

        $item = $service->resolve($registration->fresh());

        $this->assertSame('attention', $item['state']);
        $this->assertSame('Konfirmasi Jadwal Tes', $item['title']);
        $this->assertSame('Konfirmasi Jadwal Tes', $item['action_label']);

        app(TestScheduleConfirmationService::class)->confirm($registration->fresh());

        $item = $service->resolve($registration->fresh());

        $this->assertSame('processing', $item['state']);
        $this->assertSame('Ikuti Rangkaian Tes', $item['title']);
        $this->assertSame('Lihat Jadwal Tes', $item['action_label']);
    }

    public function test_completed_and_inactive_registrations_do_not_trigger_the_modal(): void
    {
        [$completed, $inactive] = $this->registrations();

        $completed->update([
            'current_stage' => 'completed',
            'data_validation_status' => 'valid',
        ]);

        $inactive->update([
            'current_stage' => 'payment',
            'data_validation_status' => 'valid',
            'lifecycle_status' => 'cancelled',
        ]);

        $service = app(ApplicantNextActionService::class);

        $this->assertNull($service->resolve($completed->fresh()));
        $this->assertNull($service->resolve($inactive->fresh()));
    }

    /**
     * @return array{Registration, Registration}
     */
    private function registrations(): array
    {
        $unit = Unit::create([
            'name' => 'SMP Contoh',
            'code' => 'SMP',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 350000,
            'status' => 'open',
        ]);

        $make = function (string $nik, string $name) use ($unit, $user, $opening): Registration {
            return Registration::create([
                'user_id' => $user->id,
                'unit_id' => $unit->id,
                'registration_opening_id' => $opening->id,
                'registrant_type' => 'parent',
                'registrant_relationship' => 'father',
                'nik' => $nik,
                'full_name' => $name,
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2012-01-01',
                'home_address' => 'Bandung',
                'status' => 'submitted',
                'current_stage' => 'data_validation',
                'data_validation_status' => 'pending',
                'lifecycle_status' => 'active',
            ]);
        };

        return [
            $make('3273010101011001', 'Calon Siswa Satu'),
            $make('3273010101011002', 'Calon Siswa Dua'),
        ];
    }
}
