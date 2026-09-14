<?php

namespace Tests\Feature;

use App\Filament\Applicant\Pages\TestSchedule;
use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\TestBooking;
use App\Models\TestSession;
use App\Models\Unit;
use App\Models\User;
use App\Services\TestBookingService;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TestScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_applicant_can_open_schedule_only_during_test_stage(): void
    {
        [$registration, $parent] = $this->fixture();

        $this->actingAs($parent)
            ->get('/pendaftar/jadwal-tes/'.$registration->uuid)
            ->assertOk()
            ->assertSeeText('Pilih Jadwal Tes');

        $registration->update(['current_stage' => 'selection']);

        $this->get('/pendaftar/jadwal-tes/'.$registration->uuid)
            ->assertForbidden();

        $registration->update([
            'current_stage' => 'tests',
            'lifecycle_status' => 'cancelled',
        ]);

        $this->get('/pendaftar/jadwal-tes/'.$registration->uuid)
            ->assertForbidden();
    }

    public function test_schedule_lists_only_bookable_sessions_and_exposes_full_capacity(): void
    {
        $this->travelTo('2026-09-14 08:00:00');
        [$registration, $parent, $session, , $test, $opening] = $this->fixture();

        $session->update(['location' => 'Ruang Aktif']);
        TestSession::create([
            'admission_test_id' => $test->id,
            'starts_at' => '2026-09-20 10:00:00',
            'ends_at' => '2026-09-20 11:00:00',
            'booking_closes_at' => '2026-09-19 10:00:00',
            'location' => 'Ruang Dibatalkan',
            'capacity' => 10,
            'status' => 'cancelled',
        ]);
        TestSession::create([
            'admission_test_id' => $test->id,
            'starts_at' => '2026-09-20 12:00:00',
            'ends_at' => '2026-09-20 13:00:00',
            'booking_closes_at' => '2026-09-14 07:00:00',
            'location' => 'Ruang Tutup',
            'capacity' => 10,
            'status' => 'active',
        ]);
        $fullSession = TestSession::create([
            'admission_test_id' => $test->id,
            'starts_at' => '2026-09-20 14:00:00',
            'ends_at' => '2026-09-20 15:00:00',
            'booking_closes_at' => '2026-09-19 14:00:00',
            'location' => 'Ruang Penuh',
            'capacity' => 1,
            'status' => 'active',
        ]);
        $otherRegistration = Registration::create([
            'user_id' => $parent->id,
            'unit_id' => $registration->unit_id,
            'registration_opening_id' => $opening->id,
            'full_name' => 'Peserta Lain',
            'nik' => '3273010101010002',
            'gender' => 'P',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-02',
            'home_address' => 'Bandung',
            'current_stage' => 'tests',
        ]);
        TestBooking::create([
            'registration_id' => $otherRegistration->id,
            'admission_test_id' => $test->id,
            'test_session_id' => $fullSession->id,
            'revision' => 1,
        ]);

        $this->actingAs($parent)
            ->get('/pendaftar/jadwal-tes/'.$registration->uuid)
            ->assertOk()
            ->assertSeeText('Ruang Aktif')
            ->assertSeeText('Ruang Penuh')
            ->assertSeeText('Sisa kuota: 0')
            ->assertDontSeeText('Ruang Dibatalkan')
            ->assertDontSeeText('Ruang Tutup');
    }

    public function test_applicant_can_choose_session_and_print_the_saved_schedule(): void
    {
        $this->travelTo('2026-09-14 08:00:00');
        [$registration, $parent, $session] = $this->fixture();
        $session->update([
            'starts_at' => '2026-09-20 08:00:00',
            'ends_at' => '2026-09-20 09:30:00',
            'booking_closes_at' => '2026-09-19 08:00:00',
            'location' => 'Ruang Ujian A',
            'instructions' => 'Bawa alat tulis.',
        ]);
        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(TestSchedule::class, ['registration' => $registration->uuid])
            ->call('choose', $session->uuid)
            ->assertNotified('Pilihan jadwal tersimpan');

        $this->assertDatabaseHas('test_bookings', [
            'registration_id' => $registration->id,
            'admission_test_id' => $session->admission_test_id,
            'test_session_id' => $session->id,
            'revision' => 1,
        ]);
        $this->assertSame(
            'scheduled',
            $registration->testResults()->where('admission_test_id', $session->admission_test_id)->value('status'),
        );

        $this->get(route('registration.test-card', $registration))
            ->assertOk()
            ->assertSeeText('20/09/2026 08:00–09:30')
            ->assertSeeText('Ruang Ujian A')
            ->assertSeeText('Bawa alat tulis.');
    }

    public function test_test_card_requires_sessions_for_every_required_test_but_ignores_optional_tests(): void
    {
        $this->travelTo('2026-09-14 08:00:00');
        [$registration, $parent, $session] = $this->fixture(true);
        $secondRequiredTest = AdmissionTest::query()
            ->where('unit_id', $registration->unit_id)
            ->where('code', 'WAW')
            ->firstOrFail();
        $secondSession = TestSession::create([
            'admission_test_id' => $secondRequiredTest->id,
            'starts_at' => '2026-09-21 10:00:00',
            'ends_at' => '2026-09-21 11:00:00',
            'booking_closes_at' => '2026-09-20 10:00:00',
            'location' => 'Ruang Wawancara',
            'capacity' => 10,
            'status' => 'active',
        ]);
        $service = app(TestBookingService::class);
        $this->actingAs($parent);

        $service->book($registration, $session, $parent);

        $this->get('/pendaftar/jadwal-tes/'.$registration->uuid)
            ->assertOk()
            ->assertDontSeeText('Cetak Kartu Tes')
            ->assertSeeText('Kartu tes dapat dicetak setelah seluruh tes wajib memiliki sesi.');
        $this->get(route('registration.test-card', $registration))
            ->assertNotFound();

        $service->book($registration, $secondSession, $parent);

        $this->get('/pendaftar/jadwal-tes/'.$registration->uuid)
            ->assertOk()
            ->assertSeeText('Cetak Kartu Tes')
            ->assertDontSeeText('Kartu tes dapat dicetak setelah seluruh tes wajib memiliki sesi.');
        $this->get(route('registration.test-card', $registration))
            ->assertOk()
            ->assertSeeText('Ruang 1')
            ->assertSeeText('Ruang Wawancara');
    }

    public function test_cancelled_selected_session_requires_rebooking_and_removes_test_card(): void
    {
        $this->travelTo('2026-09-14 08:00:00');
        [$registration, $parent, $session, $staff] = $this->fixture();
        $service = app(TestBookingService::class);
        $service->book($registration, $session, $parent);

        $service->saveSession($session, [
            'admission_test_id' => $session->admission_test_id,
            'starts_at' => $session->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $session->ends_at->format('Y-m-d H:i:s'),
            'booking_closes_at' => $session->booking_closes_at->format('Y-m-d H:i:s'),
            'location' => $session->location,
            'capacity' => $session->capacity,
            'instructions' => $session->instructions,
            'status' => 'cancelled',
        ], $staff);

        $this->assertNull(TestBooking::query()->where('registration_id', $registration->id)->value('test_session_id'));
        $this->assertSame(
            'unbooked',
            $registration->testResults()->where('admission_test_id', $session->admission_test_id)->value('status'),
        );

        $this->actingAs($parent)
            ->get('/pendaftar/jadwal-tes/'.$registration->uuid)
            ->assertOk()
            ->assertSeeText('Belum memilih sesi')
            ->assertDontSeeText('Cetak Kartu Tes')
            ->assertDontSeeText($session->location);

        $this->get(route('registration.test-card', $registration))
            ->assertNotFound();
    }

    /** @return array{Registration, User, TestSession, User, AdmissionTest, RegistrationOpening} */
    private function fixture(bool $withAdditionalTests = false): array
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'Unit Uji Jadwal',
            'code' => 'SMA-JADWAL',
            'is_active' => true,
        ]);
        $test = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akademik',
            'code' => 'AKAD',
            'is_required' => true,
            'is_active' => true,
        ]);
        $tests = [$test];

        if ($withAdditionalTests) {
            $tests[] = AdmissionTest::create([
                'unit_id' => $unit->id,
                'name' => 'Wawancara',
                'code' => 'WAW',
                'is_required' => true,
                'is_active' => true,
            ]);
            $tests[] = AdmissionTest::create([
                'unit_id' => $unit->id,
                'name' => 'Tes Minat',
                'code' => 'MINAT',
                'is_required' => false,
                'is_active' => true,
            ]);
        }

        app(UnitConfigurationService::class)->initialize($unit);

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
            'full_name' => 'Peserta Uji Jadwal',
            'nik' => '3273010101010001',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
            'current_stage' => 'tests',
        ]);

        foreach ($tests as $configuredTest) {
            AdmissionTestResult::create([
                'registration_id' => $registration->id,
                'admission_test_id' => $configuredTest->id,
                'status' => 'unbooked',
                'result' => 'pending',
            ]);
        }

        $session = TestSession::create([
            'admission_test_id' => $test->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'booking_closes_at' => now()->addDays(2),
            'location' => 'Ruang 1',
            'instructions' => 'Datang 15 menit lebih awal.',
            'capacity' => 10,
            'status' => 'active',
        ]);

        return [$registration, $parent, $session, $staff, $test, $opening];
    }
}
