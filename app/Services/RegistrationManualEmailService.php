<?php

namespace App\Services;

use App\Jobs\SendRegistrationManualEmail;
use App\Models\AdmissionTestResult;
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

    public function canSendLmsAccess(User $actor, AdmissionTestResult $result): bool
    {
        $registration = $result->registration;

        return $registration !== null
            && $this->canCompose($actor, $registration)
            && (bool) $registration->user?->hasVerifiedEmail();
    }

    public function send(
        Registration $registration,
        User $actor,
        string $subject,
        string $body,
        string $reason,
    ): MailDeliveryAttempt {
        return $this->queue($registration, $actor, $subject, $body, $reason, 'manual_message');
    }

    /**
     * Credentials are supplied by staff, not fetched or generated from Moodle.
     * Prefer a one-time activation/reset URL; an optional initial password may
     * be sent only when the external LMS process requires it.
     *
     * @param array<string, mixed> $data
     */
    public function sendLmsAccess(AdmissionTestResult $result, User $actor, array $data): MailDeliveryAttempt
    {
        $registration = $result->registration;
        abort_unless($this->canSendLmsAccess($actor, $result), 403);

        $input = Validator::make($data, [
            'platform' => ['required', 'string', 'max:100'],
            'login_url' => ['required', 'url', 'max:2000', 'regex:/^https:\/\//i'],
            'username' => ['required', 'string', 'max:150'],
            'temporary_password' => ['nullable', 'string', 'max:200'],
            'subject' => ['required', 'string', 'max:150', 'not_regex:/[\r\n]/'],
            'body' => ['required', 'string', 'max:15000'],
            'reason' => ['required', 'string', 'max:300'],
        ])->validate();

        // Escape staff-provided credentials and URL before building HTML.
        $name = e(trim($input['platform']));
        $loginUrl = e(trim($input['login_url']));
        $username = e(trim($input['username']));
        $password = $input['temporary_password'] ?? null;
        $message = app(RegistrationRichEmailHtml::class)->sanitize($input['body']);
        $message .= '<h3>Informasi Akses '. $name .'</h3>';
        $message .= '<p><strong>Alamat login:</strong> <a href="'.$loginUrl.'">'.$loginUrl.'</a></p>';
        $message .= '<p><strong>Username:</strong> '.$username.'</p>';

        if (filled($password)) {
            $message .= '<p><strong>Password sementara:</strong> '.e($password).'</p>';
            $message .= '<p>Segera ganti password setelah login pertama. Jangan bagikan informasi akun.</p>';
        } else {
            $message .= '<p>Gunakan mekanisme aktivasi atau reset password resmi dari platform untuk membuat password Anda.</p>';
        }

        return $this->queue(
            $registration,
            $actor,
            $input['subject'],
            $message,
            $input['reason'],
            'lms_access',
        );
    }

    private function queue(
        Registration $registration,
        User $actor,
        string $subject,
        string $body,
        string $reason,
        string $type,
    ): MailDeliveryAttempt {
        $data = Validator::make([
            'subject' => trim($subject),
            'body' => app(RegistrationRichEmailHtml::class)->sanitize(trim($body)),
            'reason' => trim($reason),
        ], [
            'subject' => ['required', 'string', 'max:150', 'not_regex:/[\r\n]/'],
            'body' => ['required', 'string', 'max:20000'],
            'reason' => ['required', 'string', 'max:300'],
        ])->validate();

        if (blank(trim(strip_tags($data['body'])))) {
            throw ValidationException::withMessages(['body' => 'Isi email harus memuat teks yang dapat dibaca.']);
        }

        $attempt = DB::transaction(function () use ($registration, $actor, $data, $type): MailDeliveryAttempt {
            $locked = Registration::query()->with(['user', 'unit'])
                ->whereKey($registration->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($this->canCompose($actor->fresh(), $locked), 403);

            $latest = MailDeliveryAttempt::query()->where('registration_id', $locked->id)
                ->whereIn('type', ['manual_message', 'lms_access'])->latest('id')->first();

            if ($latest && $latest->status === 'queued'
                && $latest->created_at->greaterThan(
                    now()->subMinutes($latest->type === 'lms_access' ? 120 : 15)
                )) {
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
                ->whereIn('type', ['manual_message', 'lms_access'])
                ->where('created_at', '>=', now()->subDay())->count() >= 10) {
                throw ValidationException::withMessages([
                    'subject' => 'Batas sepuluh email komunikasi untuk pendaftar ini dalam 24 jam sudah tercapai.',
                ]);
            }

            if (MailDeliveryAttempt::query()->where('requested_by', $actor->id)
                ->whereIn('type', ['manual_message', 'lms_access'])
                ->where('created_at', '>=', now()->subHour())->count() >= 30) {
                throw ValidationException::withMessages([
                    'subject' => 'Batas tiga puluh email per jam untuk petugas ini sudah tercapai.',
                ]);
            }

            $attempt = MailDeliveryAttempt::query()->create([
                'unit_id' => $locked->unit_id,
                'registration_id' => $locked->id,
                'requested_by' => $actor->id,
                'type' => $type,
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

            // Never include body, URL, username, or password in audit metadata.
            app(AuditTrail::class)->record(
                $type === 'lms_access' ? 'mail.lms_access_requested' : 'mail.manual_message_requested',
                $locked,
                metadata: ['delivery_attempt_id' => $attempt->id, 'mail_type' => $type],
                actor: $actor,
                description: 'Email komunikasi pendaftar dijadwalkan oleh petugas',
            );

            return $attempt;
        }, 5);

        try {
            SendRegistrationManualEmail::dispatch($attempt->id);
        } catch (Throwable $exception) {
            app(RegistrationEmailDeliveryService::class)->markFailed(
                $attempt->id,
                $type === 'lms_access'
                    ? 'Pengiriman akses LMS gagal. Periksa konfigurasi SMTP dan antrean.'
                    : $exception->getMessage(),
            );

            if ($type === 'lms_access') {
                $this->scrubLmsContent($attempt->id);
            }

            throw $exception;
        }

        return $attempt->fresh();
    }

    /** Remove LMS credentials after a terminal send/skip/failure. */
    public function scrubLmsContent(int $attemptId): void
    {
        $message = RegistrationManualEmail::query()
            ->where('mail_delivery_attempt_id', $attemptId)->first();

        if ($message) {
            $message->update([
                'body' => '<p>Konten kredensial LMS tidak disimpan setelah pengiriman selesai.</p>',
            ]);
        }
    }

}
