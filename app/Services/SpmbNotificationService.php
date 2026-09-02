<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\SpmbDatabaseNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SpmbNotificationService
{
    public function registrationSubmitted(Registration $registration): void
    {
        $registration->loadMissing(['user', 'unit', 'opening']);

        $this->notify(
            collect([$registration->user]),
            'registration.submitted',
            'registration',
            'Pendaftaran berhasil dikirim',
            "Data {$registration->full_name} telah masuk dengan nomor {$registration->registration_number} dan menunggu validasi.",
            'success',
            'heroicon-o-check-circle',
            'Lihat progres',
            $this->applicantStatusUrl($registration),
            $registration,
        );

        $this->notify(
            $this->staffRecipients($registration->unit_id),
            'registration.submitted_staff',
            'work_queue',
            'Pendaftaran baru perlu divalidasi',
            "{$registration->registration_number} · {$registration->full_name} telah dikirim ke {$registration->unit?->name}.",
            'info',
            'heroicon-o-clipboard-document-check',
            'Buka pendaftaran',
            $this->adminRegistrationUrl($registration),
            $registration,
        );
    }

    public function dataValidationResult(Registration $registration, bool $approved, ?string $notes = null): void
    {
        $registration->loadMissing('user');

        $this->notify(
            collect([$registration->user]),
            $approved ? 'registration.data_validated' : 'registration.data_revision_required',
            'workflow',
            $approved ? 'Data pendaftaran dinyatakan valid' : 'Data pendaftaran perlu diperbaiki',
            $approved
                ? 'Validasi data selesai. Sistem akan melanjutkan ke penerbitan Virtual Account.'
                : ('Catatan petugas: '.($notes ?: 'Silakan periksa kembali data pendaftaran.')),
            $approved ? 'success' : 'warning',
            $approved ? 'heroicon-o-check-badge' : 'heroicon-o-pencil-square',
            $approved ? 'Lihat progres' : 'Perbaiki data',
            $approved ? $this->applicantStatusUrl($registration) : url("/pendaftar/registrations/{$registration->id}/edit"),
            $registration,
            ['approved' => $approved],
        );
    }

    public function virtualAccountIssued(Payment $payment): void
    {
        $payment->loadMissing(['registration.user', 'registration.unit']);
        $registration = $payment->registration;
        $amount = number_format((float) $payment->amount, 0, ',', '.');

        $this->notify(
            collect([$registration->user]),
            'payment.virtual_account_issued',
            'payment',
            'Virtual Account telah diterbitkan',
            "VA {$payment->va_number} tersedia. Nominal formulir Rp{$amount}.",
            'success',
            'heroicon-o-credit-card',
            'Lihat pembayaran',
            url("/pendaftar/pembayaran/{$registration->id}"),
            $registration,
            ['payment_id' => $payment->id, 'amount' => (float) $payment->amount],
        );
    }

    public function virtualAccountPoolEmpty(Registration $registration): void
    {
        $registration->loadMissing('unit');

        $this->notify(
            $this->staffRecipients($registration->unit_id),
            'virtual_account.pool_empty',
            'operational',
            'Pool Virtual Account kosong',
            "{$registration->unit?->name}: pendaftaran {$registration->registration_number} tertahan karena tidak ada VA tersedia.",
            'danger',
            'heroicon-o-exclamation-triangle',
            'Buka pendaftaran',
            $this->adminRegistrationUrl($registration),
            $registration,
        );
    }

    public function paymentProofUploaded(Payment $payment): void
    {
        $payment->loadMissing(['registration.unit']);
        $registration = $payment->registration;

        $this->notify(
            $this->staffRecipients($registration->unit_id),
            'payment.proof_uploaded',
            'work_queue',
            'Bukti pembayaran menunggu verifikasi',
            "{$registration->registration_number} · {$registration->full_name} telah mengunggah bukti pembayaran.",
            'warning',
            'heroicon-o-banknotes',
            'Verifikasi pembayaran',
            url("/admin/payments/{$payment->id}/edit"),
            $registration,
            ['payment_id' => $payment->id],
        );
    }

    public function paymentVerificationResult(Payment $payment, bool $approved, ?string $reason = null): void
    {
        $payment->loadMissing(['registration.user']);
        $registration = $payment->registration;

        $this->notify(
            collect([$registration->user]),
            $approved ? 'payment.verified' : 'payment.rejected',
            'payment',
            $approved ? 'Pembayaran telah diverifikasi' : 'Bukti pembayaran ditolak',
            $approved
                ? 'Pembayaran formulir dinyatakan valid. Proses dilanjutkan ke penerbitan kartu pendaftar.'
                : ('Alasan: '.($reason ?: 'Bukti pembayaran perlu diperbaiki.')),
            $approved ? 'success' : 'danger',
            $approved ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle',
            $approved ? 'Lihat progres' : 'Unggah ulang bukti',
            $approved ? $this->applicantStatusUrl($registration) : url("/pendaftar/pembayaran/{$registration->id}"),
            $registration,
            ['payment_id' => $payment->id, 'approved' => $approved],
        );
    }

    public function applicantCardIssued(Registration $registration): void
    {
        $registration->loadMissing('user');

        $this->notify(
            collect([$registration->user]),
            'registration.card_issued',
            'workflow',
            'Kartu pendaftar tersedia',
            "Kartu {$registration->applicant_card_number} telah diterbitkan. Silakan lanjutkan kelengkapan berkas.",
            'success',
            'heroicon-o-identification',
            'Lengkapi berkas',
            url("/pendaftar/dokumen/{$registration->id}"),
            $registration,
        );
    }

    public function documentsVerified(Registration $registration, bool $hasTests): void
    {
        $registration->loadMissing('user');

        $this->notify(
            collect([$registration->user]),
            'documents.completed',
            'documents',
            'Seluruh berkas telah diverifikasi',
            $hasTests
                ? 'Berkas dinyatakan lengkap dan valid. Pendaftaran masuk ke rangkaian tes.'
                : 'Berkas dinyatakan lengkap dan valid. Pendaftaran masuk ke tahap seleksi.',
            'success',
            'heroicon-o-document-check',
            'Lihat progres',
            $this->applicantStatusUrl($registration),
            $registration,
        );
    }

    public function documentNeedsAttention(Registration $registration, string $documentName): void
    {
        $registration->loadMissing('user');

        $this->notify(
            collect([$registration->user]),
            'documents.verification_reopened',
            'documents',
            'Berkas perlu diperiksa kembali',
            "Verifikasi {$documentName} dibatalkan oleh petugas. Silakan periksa kelengkapan berkas Anda.",
            'warning',
            'heroicon-o-document-minus',
            'Lihat berkas',
            url("/pendaftar/dokumen/{$registration->id}"),
            $registration,
        );
    }

    public function testsCompleted(Registration $registration): void
    {
        $registration->loadMissing('user');

        $this->notify(
            collect([$registration->user]),
            'tests.completed',
            'tests',
            'Rangkaian tes telah selesai',
            'Seluruh tahapan tes telah tercatat. Hasil akhir akan diumumkan setelah proses seleksi selesai.',
            'info',
            'heroicon-o-clipboard-document-check',
            'Lihat progres',
            $this->applicantStatusUrl($registration),
            $registration,
        );

        $this->notify(
            $this->staffRecipients($registration->unit_id),
            'selection.ready',
            'work_queue',
            'Pendaftaran siap diputuskan',
            "{$registration->registration_number} telah menyelesaikan seluruh rangkaian tes dan siap masuk keputusan seleksi.",
            'warning',
            'heroicon-o-scale',
            'Buka pendaftaran',
            $this->adminRegistrationUrl($registration),
            $registration,
        );
    }

    public function selectionDecided(Registration $registration, string $decision): void
    {
        $this->notify(
            $this->staffRecipients($registration->unit_id),
            'selection.decided',
            'workflow',
            'Keputusan seleksi tersimpan',
            "{$registration->registration_number}: keputusan {$decision} tersimpan dan menunggu publikasi pengumuman.",
            'info',
            'heroicon-o-megaphone',
            'Buka pendaftaran',
            $this->adminRegistrationUrl($registration),
            $registration,
            ['decision' => $decision],
        );
    }

    public function announcementPublished(Announcement $announcement): void
    {
        $announcement->loadMissing('registration.user');
        $registration = $announcement->registration;
        $decision = $registration->selection()->value('decision');

        $this->notify(
            collect([$registration->user]),
            'announcement.published',
            'announcement',
            $announcement->title ?: 'Pengumuman hasil SPMB tersedia',
            $announcement->message ?: ('Keputusan seleksi: '.($decision ?: 'tersedia').'.'),
            $decision === 'accepted' ? 'success' : ($decision === 'rejected' ? 'danger' : 'warning'),
            'heroicon-o-megaphone',
            'Lihat pengumuman',
            $this->applicantStatusUrl($registration),
            $registration,
            ['announcement_id' => $announcement->id, 'decision' => $decision],
        );
    }

    public function lifecycleChanged(Registration $registration, string $status, User $actor, ?string $reason): void
    {
        $registration->loadMissing(['user', 'unit']);
        $label = Registration::LIFECYCLE_STATUSES[$status] ?? $status;
        $body = "Status pendaftaran {$registration->registration_number} berubah menjadi {$label}.";

        if ($reason) {
            $body .= " Alasan: {$reason}";
        }

        $this->notify(
            collect([$registration->user]),
            'registration.lifecycle_changed',
            'lifecycle',
            'Status pendaftaran berubah',
            $body,
            $status === 'active' ? 'success' : ($status === 'cancelled' ? 'danger' : 'warning'),
            'heroicon-o-arrow-path-rounded-square',
            'Lihat progres',
            $this->applicantStatusUrl($registration),
            $registration,
            ['lifecycle_status' => $status, 'changed_by' => $actor->id],
        );

        if ($actor->isUser()) {
            $this->notify(
                $this->staffRecipients($registration->unit_id),
                'registration.lifecycle_changed_staff',
                'work_queue',
                'Lifecycle pendaftaran berubah',
                "{$registration->registration_number} · {$registration->full_name}: {$label}.".($reason ? " Alasan: {$reason}" : ''),
                'warning',
                'heroicon-o-arrow-path-rounded-square',
                'Buka pendaftaran',
                $this->adminRegistrationUrl($registration),
                $registration,
                ['lifecycle_status' => $status, 'changed_by' => $actor->id],
            );
        }
    }

    public function securityNotice(User $user, string $event, string $title, string $body): void
    {
        $this->notify(
            collect([$user]),
            $event,
            'security',
            $title,
            $body,
            'info',
            'heroicon-o-shield-check',
            'Buka akun',
            $user->isUser() ? url('/pendaftar/profile') : url('/admin'),
            $user,
        );
    }

    public function deliveryFailure(?Model $subject, string $channel, string $message, ?int $unitId = null, ?int $registrationId = null): void
    {
        $recipients = $unitId ? $this->staffRecipients($unitId) : $this->superAdmins();

        $this->notify(
            $recipients,
            'delivery.failed',
            'operational',
            'Pengiriman notifikasi eksternal gagal',
            "Channel {$channel}: {$message}",
            'danger',
            'heroicon-o-exclamation-triangle',
            'Buka Audit Trail',
            url('/admin/audit-logs'),
            $subject,
            ['channel' => $channel],
            $unitId,
            $registrationId,
        );
    }

    private function notify(
        Collection $recipients,
        string $event,
        string $category,
        string $title,
        ?string $body,
        string $status,
        ?string $icon,
        ?string $actionLabel,
        ?string $actionUrl,
        ?Model $subject = null,
        array $metadata = [],
        ?int $unitId = null,
        ?int $registrationId = null,
    ): void {
        $recipients = $recipients
            ->filter(fn ($user): bool => $user instanceof User && (bool) $user->is_active)
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        if ($subject instanceof Registration) {
            $unitId ??= $subject->unit_id;
            $registrationId ??= $subject->id;
        } elseif ($subject instanceof Payment || $subject instanceof Announcement) {
            $registrationId ??= $subject->registration_id;
            $unitId ??= Registration::query()->whereKey($registrationId)->value('unit_id');
        }

        foreach ($recipients as $recipient) {
            $recipient->notify(new SpmbDatabaseNotification(
                event: $event,
                category: $category,
                title: $title,
                body: $body,
                status: $status,
                icon: $icon,
                actionLabel: $actionLabel,
                actionUrl: $actionUrl,
                registrationId: $registrationId,
                unitId: $unitId,
                metadata: $metadata,
            ));
        }

        app(AuditTrail::class)->record(
            'notification.queued',
            $subject,
            metadata: [
                'notification_event' => $event,
                'category' => $category,
                'recipient_ids' => $recipients->pluck('id')->all(),
                'recipient_count' => $recipients->count(),
            ] + $metadata,
            unitId: $unitId,
            registrationId: $registrationId,
            description: "Filament notification queued: {$title}",
        );
    }

    private function staffRecipients(int $unitId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($unitId): void {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))
                    ->orWhere(function ($tu) use ($unitId): void {
                        $tu->where('unit_id', $unitId)
                            ->whereHas('roles', fn ($roles) => $roles->where('name', 'tu'));
                    });
            })
            ->get();
    }

    private function superAdmins(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))
            ->get();
    }

    private function applicantStatusUrl(Registration $registration): string
    {
        return url("/pendaftar/status/{$registration->id}");
    }

    private function adminRegistrationUrl(Registration $registration): string
    {
        return url("/admin/registrations/{$registration->id}/edit");
    }
}
