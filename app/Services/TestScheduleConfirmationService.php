<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\TestBooking;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TestScheduleConfirmationService
{
    private function lockUnit(int $unitId): void
    {
        DB::table('units')->where('id', $unitId)->update(['id' => DB::raw('id')]);
    }

    /**
     * @return Collection<int, int>
     */
    public function requiredTestIds(Registration $registration): Collection
    {
        return collect($registration->configuredTests())
            ->filter(fn (array $test): bool => (bool) ($test['is_required'] ?? false))
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function bookedRequiredTestIds(Registration $registration): Collection
    {
        $requiredTestIds = $this->requiredTestIds($registration);

        if ($requiredTestIds->isEmpty()) {
            return collect();
        }

        return TestBooking::query()
            ->where('registration_id', $registration->id)
            ->whereIn('admission_test_id', $requiredTestIds->all())
            ->whereNotNull('test_session_id')
            ->pluck('admission_test_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @return array{required:int,booked:int,missing:int,complete:bool,confirmed:bool}
     */
    public function progress(Registration $registration): array
    {
        $requiredTestIds = $this->requiredTestIds($registration);
        $bookedTestIds = $this->bookedRequiredTestIds($registration);
        $missingTestIds = $requiredTestIds->diff($bookedTestIds)->values();
        $complete = $requiredTestIds->isNotEmpty() && $missingTestIds->isEmpty();

        return [
            'required' => $requiredTestIds->count(),
            'booked' => $requiredTestIds->intersect($bookedTestIds)->count(),
            'missing' => $missingTestIds->count(),
            'complete' => $complete,
            'confirmed' => $complete && filled($registration->test_schedule_confirmed_at),
        ];
    }

    public function isComplete(Registration $registration): bool
    {
        return $this->progress($registration)['complete'];
    }

    public function isConfirmed(Registration $registration): bool
    {
        return $this->progress($registration)['confirmed'];
    }

    public function confirm(Registration $registration): Registration
    {
        return DB::transaction(function () use ($registration): Registration {
            $this->lockUnit((int) $registration->unit_id);

            $lockedRegistration = Registration::query()
                ->lockForUpdate()
                ->findOrFail($registration->id);

            $lockedRegistration->assertCurrentStage('tests');

            if (! $this->isComplete($lockedRegistration)) {
                throw ValidationException::withMessages([
                    'tests' => 'Pilih jadwal untuk seluruh tes wajib sebelum mengonfirmasi.',
                ]);
            }

            $lockedRegistration->forceFill([
                'test_schedule_confirmed_at' => now(),
            ])->save();

            app(AuditTrail::class)->record(
                'test.schedule.confirmed',
                $lockedRegistration,
                newValues: ['test_schedule_confirmed_at' => $lockedRegistration->test_schedule_confirmed_at],
                unitId: $lockedRegistration->unit_id,
                registrationId: $lockedRegistration->id,
                description: 'Pendaftar mengonfirmasi seluruh jadwal tes wajib.',
            );

            return $lockedRegistration->fresh();
        }, 5);
    }

    public function invalidate(Registration $registration): void
    {
        Registration::query()
            ->whereKey($registration->id)
            ->whereNotNull('test_schedule_confirmed_at')
            ->update(['test_schedule_confirmed_at' => null]);

        $registration->setAttribute('test_schedule_confirmed_at', null);
    }
}
