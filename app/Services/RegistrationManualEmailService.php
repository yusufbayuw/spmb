<?php

namespace App\Services;

use App\Jobs\SendRegistrationManualEmail;
use App\Models\MailDeliveryAttempt;
use App\Models\Registration;
use App\Models\RegistrationManualEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrationManualEmailService
{
    public function canCompose(User $actor, Registration $registration): bool
    {
        $registration->loadMissing('user');

        return $actor->is_active
            && ($actor->isAdmin() || ($actor->isTU()
                && $actor->hasOperationalUnitAccess()
                && (int) $actor->unit_id === (int) $registration->unit_id))
            && $registration->isOperational()
            && $registration->user?->is_active
            && $registration->user->hasRole('pendaftar')
            && ! $registration->user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])
            && filter_var($registration->user->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(
        Registration $registration,
        User $actor,
        string $subject,
        string $body,
        string $reason,
    ): MailDeliveryAttempt {
        $data = Validator::make([
            'subject' => trim($subject),
            'body' => trim($body),
            'reason' => trim($reason),
        ], [
            'subject' => ['required', 'string', 'max:150', 'not_regex:/[\r\n]/'],
            'body' => ['required', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:300'],
        ])->validate();

        $attempt = DB::transaction(function () use ($registration, $actor, $data): MailDeliveryAttempt {
            $locked = Registration::query()->with(['user', 'unit'])
                ->whereKey($registration->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($this->canCompose($actor->fresh(), $locked), 403);

            $latest = MailDeliveryAttempt::query()->where('registration_id', $locked->id)
                ->where('type', 'manual_message')->latest('id')->first();

            if ($latest && $latest->status === 'queued'
                && $latest->created_at->greaterThan(now()->subMinutes(15))) {
                throw ValidationException::withMessages([
                    'subject' => 'Pesan sebelumnya masih antre. Periksa Riwayat Email terlebih dahulu.',
                ]);
            }

            if ($latest && $latest->created_at->greaterThan(now()->subMinutes(2))) {
                throw ValidationException::withMessages([
                    'subject' => 'Tunggu dua menit sebelum mengirim pesan berikutnya.',
                ]);
            }

            if (MailDeliveryAttempt::query()->where('registration_id', $locked->id)
                ->where('type', 'manual_message')
                ->where('created_at', '>=', now()->subDay())->count() >= 10) {
                throw ValidationException::withMessages([
                    'subject' => 'Batas sepuluh email komunikasi untuk pendaftar ini dalam 24 jam sudah tercapai.',
                ]);
            }

            if (MailDeliveryAttempt::query()->where('requested_by', $actor->id)
                ->where('type', 'manual_message')
                ->where('created_at', '>=', now()->subHour())->count() >= 30) {
                throw ValidationException::withMessages([
                    'subject' => 'Batas tiga puluh email per jam untuk petugas ini sudah tercapai.',
                ]);
            }

            $attempt = MailDeliveryAttempt::query()->create([
                'unit_id' => $locked->unit_id,
                'registration_id' => $locked->id,
                'requested_by' => $actor->id,
                'type' => 'manual_message',
                'origin' => 'manual',
                'status' => 'queued',
                'source_id' => $locked->id,
                'recipient_email' => $locked->user->email,
                'reason' => $data['reason'],
            ]);

            RegistrationManualEmail::query()->create([
                'mail_delivery_attempt_id' => $attempt->id,
                'subject' => $data['subject'],
                'body' => $data['body'],
            ]);

            app(AuditTrail::class)->record(
                'mail.manual_message_requested',
                $locked,
                metadata: ['delivery_attempt_id' => $attempt->id, 'mail_type' => 'manual_message'],
                actor: $actor,
                description: 'Email komunikasi pendaftar dijadwalkan oleh petugas',
            );

            return $attempt;
        }, 5);

        try {
            SendRegistrationManualEmail::dispatch($attempt->id);
        } catch (Throwable $exception) {
            app(RegistrationEmailDeliveryService::class)->markFailed($attempt->id, $exception->getMessage());
            throw $exception;
        }

        return $attempt->fresh();
    }
}
