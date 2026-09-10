<?php

namespace App\Services;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\Registration;
use App\Models\TestBooking;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TestBookingService
{
    private function lockUnit(int $unitId): void
    {
        DB::table('units')->where('id', $unitId)->update(['id' => DB::raw('id')]);
    }

    public function book(Registration $registration, TestSession $session, User $actor): TestBooking
    {
        return DB::transaction(function () use ($registration, $session, $actor): TestBooking {
            $this->lockUnit((int) $registration->unit_id);
            $registration = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            abort_unless($actor->id === $registration->user_id && $actor->is_active, 403);
            $registration->assertCurrentStage('tests');
            $session = TestSession::query()->with('admissionTest')->lockForUpdate()->findOrFail($session->id);
            $definition = collect($registration->configuredTests())->firstWhere('id', $session->admission_test_id);
            if (! $definition || $session->admissionTest->unit_id !== $registration->unit_id) {
                throw ValidationException::withMessages(['session' => 'Sesi tidak sesuai tes dan unit pendaftaran.']);
            }
            if ($registration->testResults()->where('admission_test_id', $session->admission_test_id)->whereIn('status', ['completed', 'exempted', 'absent'])->exists()) {
                throw ValidationException::withMessages(['session' => 'Tes sudah memiliki hasil akhir.']);
            }
            $booking = TestBooking::query()->where('registration_id', $registration->id)->where('admission_test_id', $session->admission_test_id)->lockForUpdate()->first();
            if ($booking?->test_session_id === $session->id) {
                AdmissionTestResult::firstOrCreate(
                    [
                        'registration_id' => $registration->id,
                        'admission_test_id' => $session->admission_test_id,
                    ],
                    [
                        'status' => 'scheduled',
                        'result' => 'pending',
                    ],
                );

                AdmissionTestResult::query()
                    ->where('registration_id', $registration->id)
                    ->where('admission_test_id', $session->admission_test_id)
                    ->whereIn('status', ['unbooked', 'scheduled'])
                    ->update(['status' => 'scheduled']);

                return $booking;
            }
            if ($session->status !== 'active' || $session->booking_closes_at->lte(now()) || $session->starts_at->lte(now())) {
                throw ValidationException::withMessages(['session' => 'Pemesanan sesi sudah ditutup.']);
            }
            if ($booking?->session && ($booking->session->booking_closes_at->lte(now()) || $booking->session->starts_at->lte(now()))) {
                throw ValidationException::withMessages(['session' => 'Batas perpindahan dari sesi lama telah berakhir.']);
            }
            if ($session->bookings()->count() >= $session->capacity) {
                throw ValidationException::withMessages(['session' => 'Kuota sesi sudah penuh. Kursi lama tetap tersimpan.']);
            }
            $conflict = TestBooking::query()->where('registration_id', $registration->id)->where('admission_test_id', '!=', $session->admission_test_id)
                ->whereHas('session', fn ($query) => $query->where('starts_at', '<', $session->ends_at)->where('ends_at', '>', $session->starts_at))->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['session' => 'Jadwal berbenturan dengan tes lain yang sudah dipilih.']);
            }
            $booking ??= new TestBooking(['registration_id' => $registration->id, 'admission_test_id' => $session->admission_test_id]);
            $booking->fill(['test_session_id' => $session->id, 'revision' => ($booking->revision ?? 0) + 1])->save();

            AdmissionTestResult::firstOrCreate(
                [
                    'registration_id' => $registration->id,
                    'admission_test_id' => $session->admission_test_id,
                ],
                [
                    'status' => 'scheduled',
                    'result' => 'pending',
                ],
            );

            AdmissionTestResult::query()
                ->where('registration_id', $registration->id)
                ->where('admission_test_id', $session->admission_test_id)
                ->whereIn('status', ['unbooked', 'scheduled'])
                ->update(['status' => 'scheduled']);

            app(SpmbNotificationService::class)->workflowEvent($registration, 'test.booking.'.$booking->id.'.'.$booking->revision, 'Jadwal tes dipilih', $definition['name'].' · '.$session->label(), true, true);

            return $booking;
        }, 5);
    }

    public function assignByStaff(AdmissionTestResult $result, TestSession $session, User $actor, string $reason): TestBooking
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penjadwalan manual wajib diisi.',
            ]);
        }

        $registration = Registration::query()->findOrFail($result->registration_id);
        app(UnitConfigurationService::class)->authorize($actor, (int) $registration->unit_id);

        return DB::transaction(function () use ($result, $session, $actor, $reason): TestBooking {
            $this->lockUnit((int) Registration::query()->whereKey($result->registration_id)->value('unit_id'));

            $result = AdmissionTestResult::query()->lockForUpdate()->findOrFail($result->id);
            $registration = Registration::query()->lockForUpdate()->findOrFail($result->registration_id);
            $registration->assertCurrentStage('tests');

            if (! in_array($result->status, ['unbooked', 'scheduled'], true)
                || $result->result !== 'pending'
                || filled($result->assessed_at)) {
                throw ValidationException::withMessages([
                    'session' => 'Tes sudah memiliki hasil akhir dan jadwal tidak dapat diubah.',
                ]);
            }

            $session = TestSession::query()
                ->with('admissionTest')
                ->lockForUpdate()
                ->findOrFail($session->id);

            $definition = collect($registration->configuredTests())->firstWhere('id', $result->admission_test_id);
            if (! $definition
                || (int) $session->admission_test_id !== (int) $result->admission_test_id
                || (int) $session->admissionTest->unit_id !== (int) $registration->unit_id) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi tidak sesuai tes dan unit pendaftaran.',
                ]);
            }

            if ($session->status !== 'active' || $session->starts_at->lte(now())) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi tidak aktif atau sudah dimulai.',
                ]);
            }

            $booking = TestBooking::query()
                ->where('registration_id', $registration->id)
                ->where('admission_test_id', $result->admission_test_id)
                ->lockForUpdate()
                ->first();

            if ($booking?->test_session_id === $session->id) {
                if ($result->status !== 'scheduled') {
                    $result->update(['status' => 'scheduled']);
                }

                return $booking;
            }

            $booking?->loadMissing('session');
            if ($booking?->session?->starts_at?->lte(now())) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi lama sudah dimulai dan tidak dapat dipindahkan.',
                ]);
            }

            if ($session->bookings()->count() >= $session->capacity) {
                throw ValidationException::withMessages([
                    'session' => 'Kuota sesi sudah penuh. Kursi lama tetap tersimpan.',
                ]);
            }

            $conflict = TestBooking::query()
                ->where('registration_id', $registration->id)
                ->where('admission_test_id', '!=', $result->admission_test_id)
                ->whereHas('session', fn ($query) => $query
                    ->where('starts_at', '<', $session->ends_at)
                    ->where('ends_at', '>', $session->starts_at))
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'session' => 'Jadwal berbenturan dengan tes lain yang sudah dipilih.',
                ]);
            }

            $oldSessionId = $booking?->test_session_id;
            $oldRevision = (int) ($booking?->revision ?? 0);
            $oldResultStatus = $result->status;
            $deadlineOverride = $session->booking_closes_at?->lte(now()) ?? false;

            $booking ??= new TestBooking([
                'registration_id' => $registration->id,
                'admission_test_id' => $result->admission_test_id,
            ]);

            $booking->fill([
                'test_session_id' => $session->id,
                'revision' => $oldRevision + 1,
            ])->save();

            $result->update([
                'status' => 'scheduled',
                'result' => 'pending',
            ]);

            $event = $oldSessionId ? 'test.booking.admin_rescheduled' : 'test.booking.admin_assigned';
            app(AuditTrail::class)->record(
                $event,
                $booking,
                oldValues: [
                    'test_session_id' => $oldSessionId,
                    'revision' => $oldRevision,
                    'result_status' => $oldResultStatus,
                ],
                newValues: [
                    'test_session_id' => $session->id,
                    'revision' => $booking->revision,
                    'result_status' => 'scheduled',
                ],
                metadata: [
                    'admission_test_id' => $result->admission_test_id,
                    'reason' => $reason,
                    'deadline_override' => $deadlineOverride,
                ],
                actor: $actor,
                unitId: $registration->unit_id,
                registrationId: $registration->id,
                description: $oldSessionId
                    ? 'Jadwal tes dipindahkan secara manual oleh petugas.'
                    : 'Jadwal tes ditetapkan secara manual oleh petugas.',
            );

            app(SpmbNotificationService::class)->workflowEvent(
                $registration,
                $event.'.'.$booking->id.'.'.$booking->revision,
                $oldSessionId ? 'Jadwal tes diubah panitia' : 'Jadwal tes ditetapkan panitia',
                $definition['name'].' · '.$session->label().'. Silakan cetak ulang kartu tes untuk memastikan jadwal terbaru.',
                true,
                false,
            );

            return $booking->fresh(['session', 'admissionTest']);
        }, 5);
    }

    public function saveSession(?TestSession $session, array $data, User $actor): TestSession
    {
        $test = AdmissionTest::findOrFail($session?->admission_test_id ?? $data['admission_test_id']);
        app(UnitConfigurationService::class)->authorize($actor, (int) $test->unit_id);
        $data['admission_test_id'] = $test->id;
        if (empty($data['booking_closes_at']) && ! empty($data['starts_at'])) {
            $data['booking_closes_at'] = Carbon::parse($data['starts_at'])->subDay()->toDateTimeString();
        }
        $data = Validator::make($data, [
            'admission_test_id' => ['required', 'integer'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'booking_closes_at' => ['required', 'date', 'before_or_equal:starts_at'], 'capacity' => ['required', 'integer', 'min:1'],
            'location' => ['required', 'string', 'max:255'], 'instructions' => ['nullable', 'string', 'max:5000'], 'status' => ['required', Rule::in(['active', 'closed', 'cancelled'])],
        ])->validate();

        return DB::transaction(function () use ($session, $data, $test): TestSession {
            $this->lockUnit((int) $test->unit_id);
            $record = $session ? TestSession::query()->lockForUpdate()->findOrFail($session->id) : new TestSession;
            $bookings = $record->exists ? $record->bookings()->with('registration')->get() : collect();
            if ($bookings->count() > $data['capacity']) {
                throw ValidationException::withMessages(['capacity' => 'Kuota tidak boleh lebih kecil dari jumlah peserta.']);
            }
            if ($record->exists && $record->starts_at->lte(now()) && $bookings->isNotEmpty()) {
                throw ValidationException::withMessages(['starts_at' => 'Sesi berpeserta yang sudah dimulai tidak dapat diubah.']);
            }
            foreach ($bookings as $booking) {
                if (TestBooking::query()->where('registration_id', $booking->registration_id)->where('id', '!=', $booking->id)->whereHas('session', fn ($q) => $q->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at']))->exists()) {
                    throw ValidationException::withMessages(['starts_at' => 'Perubahan jadwal berbenturan dengan tes peserta.']);
                }
            }
            if ($record->status === 'cancelled' && $data['status'] !== 'cancelled') {
                throw ValidationException::withMessages(['status' => 'Sesi yang dibatalkan tidak dapat diaktifkan kembali. Buat sesi baru.']);
            }
            $record->fill($data);
            $changed = $record->isDirty(['starts_at', 'ends_at', 'location', 'status', 'instructions']);
            $record->save();
            if ($changed) {
                foreach ($bookings as $booking) {
                    if ($record->status === 'cancelled') {
                        $booking->update(['test_session_id' => null, 'revision' => $booking->revision + 1]);

                        AdmissionTestResult::query()
                            ->where('registration_id', $booking->registration_id)
                            ->where('admission_test_id', $booking->admission_test_id)
                            ->where('status', 'scheduled')
                            ->update(['status' => 'unbooked']);
                    }
                    app(SpmbNotificationService::class)->workflowEvent($booking->registration, 'test.session.changed', $record->status === 'cancelled' ? 'Sesi tes dibatalkan' : 'Informasi sesi tes berubah', $record->label().($record->status === 'cancelled' ? '. Silakan pilih sesi baru.' : ''), true, true);
                }
            }
            app(AuditTrail::class)->record('test.session.saved', $record, unitId: $test->unit_id);

            return $record;
        }, 5);
    }

    public function release(Registration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            $this->lockUnit((int) $registration->unit_id);
            $bookings = TestBooking::query()->where('registration_id', $registration->id)->whereHas('session', fn ($q) => $q->where('starts_at', '>', now()))->get();
            foreach ($bookings as $booking) {
                $booking->update(['test_session_id' => null, 'revision' => $booking->revision + 1]);

                AdmissionTestResult::query()
                    ->where('registration_id', $booking->registration_id)
                    ->where('admission_test_id', $booking->admission_test_id)
                    ->where('status', 'scheduled')
                    ->update(['status' => 'unbooked']);
            }
        }, 5);
    }
}
