<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\TestBooking;
use App\Models\TestSession;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReceiptService;
use App\Services\RegistrationWorkflowService;
use App\Services\TestBookingService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TestBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_is_idempotent_and_full_destination_preserves_old_seat(): void
    {
        [$registration, $parent, $session] = $this->fixture();
        $service = app(TestBookingService::class);

        $this->assertSame(
            'unbooked',
            $registration->testResults()->where('admission_test_id', $session->admission_test_id)->value('status'),
        );

        $booking = $service->book($registration, $session, $parent);

        $this->assertSame(
            'scheduled',
            $registration->testResults()->where('admission_test_id', $session->admission_test_id)->value('status'),
        );
        $this->assertSame($booking->id, $service->book($registration, $session, $parent)->id);
        $other = $session->replicate();
        $other->starts_at = now()->addDays(4);
        $other->ends_at = now()->addDays(4)->addHour();
        $other->save();
        $second = $registration->replicate();
        $second->nik = '3273010101010002';
        $second->registration_number = null;
        $second->save();
        $service->book($second, $other, $parent);
        try {
            $service->book($registration, $other, $parent);
            $this->fail('Full session accepted a booking');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('session', $exception->errors());
        }
        $this->assertSame($session->id, $booking->fresh()->test_session_id);
        $this->assertSame(1, $other->bookings()->count());
    }

    public function test_test_card_button_is_hidden_until_a_session_is_selected(): void
    {
        [$registration, $parent, $session] = $this->fixture();

        $this->actingAs($parent)
            ->get('/pendaftar/jadwal-tes/'.$registration->uuid)
            ->assertOk()
            ->assertDontSeeText('Cetak Kartu Tes');

        app(TestBookingService::class)->book($registration, $session, $parent);

        $this->actingAs($parent)
            ->get('/pendaftar/jadwal-tes/'.$registration->uuid)
            ->assertOk()
            ->assertSeeText('Cetak Kartu Tes');
    }

    public function test_rescheduling_releases_old_seat_and_changes_printed_card(): void
    {
        [$registration, $parent, $session] = $this->fixture();
        $service = app(TestBookingService::class);
        $service->book($registration, $session, $parent);
        $other = $session->replicate();
        $other->location = 'Ruang Baru';
        $other->save();
        $service->book($registration, $other, $parent);

        $this->assertSame(0, $session->bookings()->count());
        $this->actingAs($parent)->get(route('registration.test-card', $registration))->assertOk()->assertSeeText('Ruang Baru');
    }

    public function test_cancelled_session_releases_seats_and_notifies_affected_parent(): void
    {
        [$registration, $parent, $session, $staff] = $this->fixture();
        $service = app(TestBookingService::class);
        $booking = $service->book($registration, $session, $parent);
        $service->saveSession($session, array_replace($session->toArray(), ['status' => 'cancelled']), $staff);
        $this->assertNull($booking->fresh()->test_session_id);
        $this->assertSame(
            'unbooked',
            $registration->testResults()->where('admission_test_id', $session->admission_test_id)->value('status'),
        );
        $this->assertTrue($parent->notifications()->where('data->title', 'Sesi tes dibatalkan')->exists());
    }

    public function test_cannot_choose_overlapping_session_or_change_after_deadline(): void
    {
        [$registration, $parent, $session] = $this->fixture();
        $service = app(TestBookingService::class);
        $service->book($registration, $session, $parent);
        $secondTest = AdmissionTest::create(['unit_id' => $registration->unit_id, 'name' => 'Wawancara', 'is_active' => true, 'is_required' => true]);
        $secondSession = $session->replicate();
        $secondSession->admission_test_id = $secondTest->id;
        $secondSession->save();
        try {
            $service->book($registration, $secondSession, $parent);
            $this->fail('Overlapping booking accepted');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('berbenturan', $exception->getMessage());
        }
        $this->travel(3)->days();
        $this->expectException(ValidationException::class);
        $service->book($registration, $secondSession, $parent);
    }

    public function test_cancellation_of_registration_releases_future_seats(): void
    {
        [$registration, $parent, $session, $staff] = $this->fixture();
        app(TestBookingService::class)->book($registration, $session, $parent);
        $registration->changeLifecycle('cancelled', $staff, 'Dibatalkan');
        $this->assertSame(0, $session->bookings()->count());
        $registration->changeLifecycle('active', $staff);
        $this->assertNull(TestBooking::first()->test_session_id);
        $this->assertSame(
            'unbooked',
            $registration->testResults()->where('admission_test_id', $session->admission_test_id)->value('status'),
        );
    }

    public function test_parent_cannot_access_another_registration_test_card(): void
    {
        [$registration, $parent, $session] = $this->fixture();
        app(TestBookingService::class)->book($registration, $session, $parent);
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('pendaftar');
        $this->actingAs($other)->get(route('registration.test-card', $registration))->assertNotFound();
    }

    public function test_missing_required_test_result_blocks_selection_but_optional_test_does_not(): void
    {
        [$registration, $parent, $session, $staff] = $this->fixture();

        $required = AdmissionTest::create([
            'unit_id' => $registration->unit_id,
            'name' => 'Wawancara',
            'is_required' => true,
            'is_active' => true,
        ]);
        AdmissionTest::create([
            'unit_id' => $registration->unit_id,
            'name' => 'Minat',
            'is_required' => false,
            'is_active' => true,
        ]);

        $bookingService = app(TestBookingService::class);
        $bookingService->book($registration, $session, $parent);

        $result = $registration->testResults()
            ->where('admission_test_id', $session->admission_test_id)
            ->firstOrFail();

        $workflow = app(RegistrationWorkflowService::class);
        $workflow->recordTestResult($result, $staff, ['status' => 'completed', 'result' => 'pass']);

        $this->assertSame('tests', $registration->fresh()->current_stage);

        $secondSession = TestSession::create([
            'admission_test_id' => $required->id,
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHour(),
            'booking_closes_at' => now()->addDays(3),
            'location' => 'Ruang 2',
            'capacity' => 1,
            'status' => 'active',
        ]);

        $bookingService->book($registration, $secondSession, $parent);

        $secondResult = $registration->testResults()
            ->where('admission_test_id', $required->id)
            ->firstOrFail();

        $workflow->recordTestResult($secondResult, $staff, ['status' => 'completed', 'result' => 'pass']);

        $this->assertSame('selection', $registration->fresh()->current_stage);
    }

    public function test_portal_pages_and_print_documents_render_for_their_authorized_users(): void
    {
        [$registration, $parent, $session, $staff] = $this->fixture();
        $registration->update(['applicant_card_number' => 'KARTU-SMA-2026-0001']);
        app(TestBookingService::class)->book($registration, $session, $parent);
        $payment = Payment::create(['registration_id' => $registration->id, 'status' => 'verified', 'amount' => 350000, 'verified_at' => now()]);
        $receipt = app(ReceiptService::class)->issue($payment);
        $this->actingAs($parent);
        $pages = [
            'schedule' => '/pendaftar/jadwal-tes/'.$registration->uuid,
            'status' => '/pendaftar/status/'.$registration->uuid,
            'card' => route('registration.card', $registration),
            'test-card' => route('registration.test-card', $registration),
            'receipt' => route('registration.receipt', [$registration, $receipt]),
        ];
        foreach ($pages as $name => $url) {
            $response = $this->get($url)->assertOk();
            $this->capturePage($name, $response->getContent());
        }

        $adminUnit = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $registration->unit_id,
            'is_active' => true,
        ]);
        $adminUnit->assignRole('admin_unit');

        $this->actingAs($adminUnit);
        foreach (['sessions' => '/admin/test-sessions', 'settings' => '/admin/unit-registration-settings'] as $name => $url) {
            $response = $this->get($url)->assertOk();
            $this->capturePage($name, $response->getContent());
        }
    }

    private function capturePage(string $name, string $html): void
    {
        $directory = getenv('SPMB_UI_CAPTURE_DIR');
        if (! $directory) {
            return;
        }
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/'.$name.'.html', $html);
    }

    private function fixture(): array
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'Unit Test', 'code' => 'SMA', 'is_active' => true]);
        $parent = User::factory()->create(['is_active' => true]);
        $parent->assignRole('pendaftar');
        $staff = User::factory()->create(['role' => 'tu', 'unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('tu');
        $opening = RegistrationOpening::create(['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1', 'status' => 'open']);
        $registration = Registration::create(['user_id' => $parent->id, 'unit_id' => $unit->id, 'registration_opening_id' => $opening->id, 'full_name' => 'Peserta Test', 'nik' => '3273010101010001', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2010-01-01', 'home_address' => 'Bandung', 'current_stage' => 'tests']);
        $test = AdmissionTest::create(['unit_id' => $unit->id, 'name' => 'Tes Akademik', 'is_required' => true, 'is_active' => true]);
        $session = TestSession::create(['admission_test_id' => $test->id, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'booking_closes_at' => now()->addDays(2), 'location' => 'Ruang 1', 'capacity' => 1, 'status' => 'active']);

        AdmissionTestResult::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $test->id,
            'status' => 'unbooked',
            'result' => 'pending',
        ]);

        return [$registration, $parent, $session, $staff];
    }
}
