<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\ReRegistrationItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReRegistrationService
{
    public function initialize(Registration $registration): void
    {
        foreach ($registration->reRegistrationRequirements() as $requirement) {
            ReRegistrationItem::firstOrCreate(
                ['registration_id' => $registration->id, 'requirement_key' => $requirement['key']],
                [
                    'label' => $requirement['label'],
                    'type' => $requirement['type'] ?? 'checklist',
                    'is_required' => (bool) ($requirement['required'] ?? false),
                ],
            );
        }
    }

    public function verify(ReRegistrationItem $item, User $staff, bool $approved, ?string $reason = null): void
    {
        if (! $approved && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan wajib diisi.']);
        }

        $completed = DB::transaction(function () use ($item, $staff, $approved, $reason): ?Registration {
            $lockedItem = ReRegistrationItem::query()->lockForUpdate()->findOrFail($item->id);
            $registration = Registration::query()->lockForUpdate()->findOrFail($lockedItem->registration_id);
            abort_if($staff->isTU() && $staff->unit_id !== $registration->unit_id, 403);
            $registration->assertCurrentStage('re_registration');
            $lockedItem->update([
                'status' => $approved ? 'verified' : 'rejected',
                'verified_by' => $staff->id,
                'verified_at' => now(),
                'rejection_reason' => $approved ? null : trim((string) $reason),
            ]);

            if (! $registration->reRegistrationComplete()) {
                return null;
            }

            $registration->transitionTo('enrollment', ['re_registration_completed_at' => now()]);
            app(AuditTrail::class)->record('reregistration.completed', $registration, actor: $staff, description: 'Seluruh persyaratan daftar ulang telah diverifikasi');

            return $registration->fresh('user');
        }, 5);

        if ($completed) {
            app(SpmbNotificationService::class)->reRegistrationCompleted($completed);
        }
    }

    public function enroll(Registration $registration, User $staff): void
    {
        DB::transaction(function () use ($registration, $staff): void {
            $lockedRegistration = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            abort_if($staff->isTU() && $staff->unit_id !== $lockedRegistration->unit_id, 403);
            $lockedRegistration->assertCurrentStage('enrollment');
            if (! $lockedRegistration->reRegistrationComplete()) {
                throw ValidationException::withMessages(['re_registration' => 'Daftar ulang belum lengkap dan terverifikasi.']);
            }

            $lockedRegistration->transitionTo('completed', [
                'status' => 'enrolled',
                'enrolled_at' => now(),
                'enrolled_by' => $staff->id,
            ]);
            app(AuditTrail::class)->record('enrollment.completed', $lockedRegistration, actor: $staff, description: 'Pendaftar resmi dienroll');
        }, 5);

        app(SpmbNotificationService::class)->enrollmentCompleted($registration->fresh('user'));
    }
}
