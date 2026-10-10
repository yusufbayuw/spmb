<?php

namespace App\Services;

use App\Jobs\SendApplicantEmailChangeConfirmation;
use App\Models\MailDeliveryAttempt;
use App\Models\PendingApplicantEmailChange;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApplicantEmailCorrectionService
{
    public function request(Registration $registration, User $actor, string $newEmail, string $nik, string $birthDate, string $reason): PendingApplicantEmailChange
    {
        abort_unless(app(RegistrationEmailDeliveryService::class)->canManage($actor, $registration), 403);

        $newEmail = mb_strtolower(trim($newEmail));
        Validator::make(['email' => $newEmail, 'reason' => trim($reason)], [
            'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'],
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        [$change, $attempt] = DB::transaction(function () use ($registration, $actor, $newEmail, $nik, $birthDate, $reason): array {
            $locked = Registration::query()->with('user')->whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless(app(RegistrationEmailDeliveryService::class)->canManage($actor->fresh(), $locked), 403);
            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_active && $user->hasRole('pendaftar')
                && ! $user->hasAnyRole(['super_admin', 'admin_unit', 'tu']), 403);

            if (! $actor->isAdmin() && Registration::query()->where('user_id', $user->id)
                ->where('unit_id', '<>', $locked->unit_id)->exists()) {
                abort(403, 'Akun lintas unit memerlukan Super Admin.');
            }

            if (! hash_equals((string) $locked->nik, trim($nik))
                || ! hash_equals((string) $locked->birth_date?->format('Y-m-d'), $birthDate)) {
                throw ValidationException::withMessages(['nik' => 'Identitas pendaftaran tidak sesuai.']);
            }
            if ($newEmail === mb_strtolower((string) $user->email)
                || User::query()->where('email', $newEmail)->exists()) {
                throw ValidationException::withMessages(['new_email' => 'Alamat email tidak tersedia.']);
            }

            $latest = PendingApplicantEmailChange::query()->where('user_id', $user->id)->latest('id')->first();
            if ($latest && $latest->created_at->greaterThan(now()->subMinutes(2))) {
                throw ValidationException::withMessages(['new_email' => 'Tunggu dua menit sebelum mengajukan koreksi lagi.']);
            }
            if (PendingApplicantEmailChange::query()->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDay())->count() >= 5) {
                throw ValidationException::withMessages(['new_email' => 'Batas koreksi harian tercapai.']);
            }

            PendingApplicantEmailChange::query()->where('user_id', $user->id)
                ->where('status', 'pending')->update(['status' => 'replaced']);

            $change = PendingApplicantEmailChange::create([
                'user_id' => $user->id, 'registration_id' => $locked->id, 'unit_id' => $locked->unit_id,
                'requested_by' => $actor->id, 'old_email' => $user->email, 'new_email' => $newEmail,
                'reason' => trim($reason), 'status' => 'pending', 'expires_at' => now()->addMinutes(30),
            ]);
            $attempt = MailDeliveryAttempt::create([
                'unit_id' => $locked->unit_id, 'registration_id' => $locked->id,
                'requested_by' => $actor->id, 'type' => 'email_change', 'origin' => 'manual',
                'status' => 'queued', 'source_id' => $change->id, 'recipient_email' => $newEmail,
                'reason' => 'Konfirmasi perubahan email oleh pemilik akun',
            ]);
            app(AuditTrail::class)->record('applicant.email_change_requested', $locked,
                actor: $actor, metadata: ['request_id' => $change->id, 'attempt_id' => $attempt->id],
                description: 'Petugas mengajukan koreksi email setelah memeriksa identitas');
            return [$change, $attempt];
        }, 5);

        try {
            SendApplicantEmailChangeConfirmation::dispatch($change->id, $attempt->id);
        } catch (Throwable $e) {
            app(RegistrationEmailDeliveryService::class)->markFailed($attempt->id, $e->getMessage());
            throw $e;
        }

        return $change;
    }

    public function confirm(PendingApplicantEmailChange $change, User $actor): void
    {
        DB::transaction(function () use ($change, $actor): void {
            $locked = PendingApplicantEmailChange::query()->whereKey($change->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->id === $locked->user_id && $actor->hasRole('pendaftar') && $actor->is_active, 403);
            abort_unless($locked->status === 'pending' && $locked->expires_at->isFuture(), 403);
            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            abort_unless($user->email === $locked->old_email && $user->is_active, 403);

            if (User::query()->where('email', $locked->new_email)->whereKeyNot($user->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'Email sudah digunakan oleh akun lain.']);
            }

            // Signed link proves new mailbox ownership; login proves account ownership.
            $user->forceFill(['email' => $locked->new_email, 'email_verified_at' => now()])->save();
            $locked->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            PendingApplicantEmailChange::query()->where('user_id', $user->id)
                ->where('status', 'pending')->whereKeyNot($locked->id)->update(['status' => 'replaced']);

            app(AuditTrail::class)->record('applicant.email_change_confirmed', $locked->registration,
                actor: $user, metadata: ['request_id' => $locked->id],
                description: 'Pendaftar mengonfirmasi alamat email baru');
        }, 5);
    }
}
