<?php

namespace App\Services;

use App\Models\AdmissionOffer;
use App\Models\Announcement;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\ReRegistrationItem;
use App\Models\Unit;
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

        $approvedBody = match ($registration->current_stage) {
            'virtual_account' => 'Validasi data selesai. Sistem akan melanjutkan ke penerbitan Virtual Account.',
            'applicant_card' => 'Validasi data selesai. Pembayaran tidak diperlukan untuk unit ini dan proses dilanjutkan ke penerbitan kartu pendaftar.',
            default => 'Validasi data selesai. Proses dilanjutkan ke '.$registration->stageLabel().'.',
        };

        $this->notify(
            collect([$registration->user]),
            $approved ? 'registration.data_validated' : 'registration.data_revision_required',
            'workflow',
            $approved ? 'Data pendaftaran dinyatakan valid' : 'Data pendaftaran perlu diperbaiki',
            $approved
                ? $approvedBody
                : ('Catatan petugas: '.($notes ?: 'Silakan periksa kembali data pendaftaran.')),
            $approved ? 'success' : 'warning',
            $approved ? 'heroicon-o-check-badge' : 'heroicon-o-pencil-square',
            $approved ? 'Lihat progres' : 'Perbaiki data',
            $approved ? $this->applicantStatusUrl($registration) : url("/pendaftar/registrations/{$registration->uuid}/edit"),
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
            url("/pendaftar/pembayaran/{$registration->uuid}"),
            $registration,
            ['payment_uuid' => $payment->uuid, 'amount' => (float) $payment->amount],
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
            url("/admin/payments/{$payment->uuid}/edit"),
            $registration,
            ['payment_uuid' => $payment->uuid],
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
            $approved ? $this->applicantStatusUrl($registration) : url("/pendaftar/pembayaran/{$registration->uuid}"),
            $registration,
            ['payment_uuid' => $payment->uuid, 'approved' => $approved],
        );
    }

    public function applicantCardIssued(Registration $registration): void
    {
        $registration->loadMissing('user');

        [$body, $actionLabel, $actionUrl] = match ($registration->current_stage) {
            'documents' => [
                "Kartu {$registration->applicant_card_number} telah diterbitkan. Silakan lanjutkan kelengkapan berkas.",
                'Lengkapi berkas',
                url("/pendaftar/dokumen/{$registration->uuid}"),
            ],
            'tests' => [
                "Kartu {$registration->applicant_card_number} telah diterbitkan. Proses dilanjutkan ke rangkaian tes.",
                'Lihat progres',
                $this->applicantStatusUrl($registration),
            ],
            'selection' => [
                "Kartu {$registration->applicant_card_number} telah diterbitkan. Persyaratan awal selesai dan pendaftaran masuk tahap seleksi.",
                'Lihat progres',
                $this->applicantStatusUrl($registration),
            ],
            default => [
                "Kartu {$registration->applicant_card_number} telah diterbitkan.",
                'Lihat progres',
                $this->applicantStatusUrl($registration),
            ],
        };

        $this->notify(
            collect([$registration->user]),
            'registration.card_issued',
            'workflow',
            'Kartu pendaftar tersedia',
            $body,
            'success',
            'heroicon-o-identification',
            $actionLabel,
            $actionUrl,
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

    public function documentNeedsAttention(Registration $registration, string $documentName, ?string $reason = null): void
    {
        $registration->loadMissing('user');

        $this->notify(
            collect([$registration->user]),
            'documents.verification_reopened',
            'documents',
            'Berkas perlu diperiksa kembali',
            $reason
                ? "Berkas {$documentName} ditolak. Alasan: {$reason}. Silakan unggah berkas perbaikan."
                : "Verifikasi {$documentName} dibatalkan oleh petugas. Silakan periksa kelengkapan berkas Anda.",
            'warning',
            'heroicon-o-document-minus',
            'Lihat berkas',
            url("/pendaftar/dokumen/{$registration->uuid}"),
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
        $body = $registration->current_stage === 'announcement'
            ? "{$registration->registration_number}: keputusan {$decision} sudah final dan menunggu publikasi hasil."
            : "{$registration->registration_number}: keputusan {$decision} telah dicatat untuk proses review/finalisasi.";

        $this->notify(
            $this->staffRecipients($registration->unit_id),
            'selection.decided',
            'workflow',
            'Keputusan seleksi tersimpan',
            $body,
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
            'selection.published',
            'announcement',
            $announcement->title ?: 'Pengumuman hasil SPMB tersedia',
            $announcement->message ?: ('Keputusan seleksi: '.($decision ?: 'tersedia').'.'),
            $decision === 'accepted' ? 'success' : ($decision === 'rejected' ? 'danger' : 'warning'),
            'heroicon-o-megaphone',
            'Lihat pengumuman',
            $this->applicantStatusUrl($registration),
            $registration,
            ['announcement_uuid' => $announcement->uuid, 'decision' => $decision],
        );
    }

    public function admissionOffer(AdmissionOffer $offer, string $event): void
    {
        $offer->loadMissing('registration.user');
        $registration = $offer->registration;
        $content = match ($event) {
            'created' => ['admission.offer.created', 'Penawaran penerimaan tersedia', 'Anda dinyatakan diterima. Konfirmasikan kursi sebelum '.$offer->expires_at->translatedFormat('d F Y H:i').'.', 'success'],
            'accepted' => [
                'admission.offer.accepted',
                'Kursi telah dikonfirmasi',
                $registration->current_stage === 'enrollment'
                    ? 'Konfirmasi penerimaan berhasil. Tidak ada persyaratan daftar ulang yang tertunda dan pendaftaran siap untuk enrollment.'
                    : 'Konfirmasi penerimaan berhasil. Silakan lengkapi daftar ulang.',
                'success',
            ],
            'declined' => ['admission.offer.declined', 'Penawaran penerimaan ditolak', 'Penawaran telah ditolak dan kursi dilepas.', 'warning'],
            'reminder' => ['admission.offer.reminder', 'Pengingat konfirmasi kursi', 'Konfirmasikan kursi sebelum '.$offer->expires_at->translatedFormat('d F Y H:i').'.', 'warning'],
            default => ['admission.offer.expired', 'Penawaran penerimaan berakhir', 'Batas konfirmasi kursi telah berakhir dan kursi dilepas.', 'danger'],
        };

        $this->notify(
            collect([$registration->user]),
            $content[0],
            'admission',
            $content[1],
            $content[2],
            $content[3],
            'heroicon-o-academic-cap',
            'Buka status',
            $this->applicantStatusUrl($registration),
            $offer,
            ['admission_offer_uuid' => $offer->uuid],
            $registration->unit_id,
            $registration->id,
        );

        $staffContent = match ($event) {
            'accepted' => [
                'admission.offer.accepted_staff',
                'Pendaftar mengonfirmasi kursi',
                "{$registration->registration_number} · {$registration->full_name} menerima penawaran. Tahap saat ini: {$registration->stageLabel()}.",
                'success',
            ],
            'declined' => [
                'admission.offer.declined_staff',
                'Pendaftar menolak penawaran kursi',
                "{$registration->registration_number} · {$registration->full_name} menolak penawaran.".($offer->decline_reason ? " Alasan: {$offer->decline_reason}" : ''),
                'warning',
            ],
            'expired' => [
                'admission.offer.expired_staff',
                'Penawaran penerimaan berakhir',
                "{$registration->registration_number} · {$registration->full_name} tidak mengonfirmasi kursi sampai batas waktu berakhir.",
                'warning',
            ],
            default => null,
        };

        if ($staffContent) {
            $this->notify(
                $this->staffRecipients($registration->unit_id),
                $staffContent[0],
                'admission',
                $staffContent[1],
                $staffContent[2],
                $staffContent[3],
                'heroicon-o-academic-cap',
                'Buka pendaftaran',
                $this->adminRegistrationUrl($registration),
                $offer,
                ['admission_offer_uuid' => $offer->uuid],
                $registration->unit_id,
                $registration->id,
            );
        }
    }

    public function waitlistPromoted(AdmissionOffer $offer): void
    {
        $offer->loadMissing('registration.user');
        $registration = $offer->registration;
        $this->notify(
            collect([$registration->user]),
            'waitlist.promoted',
            'admission',
            'Anda dipromosikan dari daftar tunggu',
            'Kursi penerimaan tersedia. Konfirmasikan sebelum '.$offer->expires_at->translatedFormat('d F Y H:i').'.',
            'success',
            'heroicon-o-arrow-trending-up',
            'Konfirmasikan kursi',
            $this->applicantStatusUrl($registration),
            $offer,
            ['admission_offer_uuid' => $offer->uuid],
            $registration->unit_id,
            $registration->id,
        );

        $this->notify(
            $this->staffRecipients($registration->unit_id),
            'waitlist.promoted_staff',
            'admission',
            'Daftar tunggu dipromosikan',
            "{$registration->registration_number} · {$registration->full_name} dipromosikan dan menerima penawaran kursi.",
            'info',
            'heroicon-o-arrow-trending-up',
            'Buka pendaftaran',
            $this->adminRegistrationUrl($registration),
            $offer,
            ['admission_offer_uuid' => $offer->uuid],
            $registration->unit_id,
            $registration->id,
        );
    }

    public function reRegistrationItemReviewed(ReRegistrationItem $item, bool $approved, ?string $reason = null): void
    {
        $item->loadMissing('registration.user');
        $registration = $item->registration;

        $this->notify(
            collect([$registration->user]),
            $approved ? 'reregistration.item_verified' : 'reregistration.item_rejected',
            'reregistration',
            $approved ? 'Persyaratan daftar ulang diverifikasi' : 'Persyaratan daftar ulang perlu diperbaiki',
            $approved
                ? "{$item->label} telah diverifikasi oleh petugas."
                : "{$item->label} ditolak. Alasan: ".($reason ?: 'Silakan periksa kembali persyaratan daftar ulang.'),
            $approved ? 'success' : 'warning',
            $approved ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle',
            $approved ? 'Lihat daftar ulang' : 'Perbaiki daftar ulang',
            url("/pendaftar/daftar-ulang/{$registration->uuid}"),
            $item,
            ['reregistration_item_uuid' => $item->uuid, 'approved' => $approved],
            $registration->unit_id,
            $registration->id,
        );
    }

    public function reRegistrationCompleted(Registration $registration): void
    {
        $registration->loadMissing('user');
        $this->workflowEvent($registration, 'reregistration.completed', 'Daftar ulang telah lengkap', 'Seluruh persyaratan daftar ulang telah diverifikasi. Pendaftaran siap untuk enrollment.', true, true);
    }

    public function reRegistrationStarted(Registration $registration): void
    {
        $registration->loadMissing('user');

        $body = $registration->current_stage === 'enrollment'
            ? 'Konfirmasi kursi berhasil. Tidak ada persyaratan daftar ulang yang tertunda dan pendaftaran siap untuk enrollment.'
            : 'Konfirmasi kursi berhasil. Silakan lengkapi persyaratan daftar ulang.';

        $this->workflowEvent($registration, 'reregistration.started', 'Daftar ulang dimulai', $body, true, false);
    }

    public function enrollmentCompleted(Registration $registration): void
    {
        $registration->loadMissing('user');
        $this->workflowEvent($registration, 'enrollment.completed', 'Enrollment berhasil', 'Anda telah resmi terdaftar sebagai peserta didik.', true, true);
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
            ['lifecycle_status' => $status, 'changed_by_uuid' => $actor->uuid],
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
                ['lifecycle_status' => $status, 'changed_by_uuid' => $actor->uuid],
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

    public function workflowEvent(Registration $registration, string $event, string $title, string $body, bool $applicant = true, bool $staff = false): void
    {
        if ($applicant) {
            $this->notify(collect([$registration->user]), $event, 'workflow', $title, $body, 'info', 'heroicon-o-bell', 'Lihat progres', $this->applicantStatusUrl($registration), $registration);
        }
        if ($staff) {
            $this->notify($this->staffRecipients($registration->unit_id), $event.'.staff', 'work_queue', $title, $body, 'info', 'heroicon-o-bell', 'Lihat pendaftaran', $this->adminRegistrationUrl($registration), $registration);
        }
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

        $registration = null;
        if ($subject instanceof Registration) {
            $registration = $subject;
            $unitId ??= $subject->unit_id;
            $registrationId ??= $subject->id;
        } elseif ($subject instanceof Payment || $subject instanceof Announcement || $subject instanceof AdmissionOffer) {
            $registrationId ??= $subject->registration_id;
            $unitId ??= Registration::query()->whereKey($registrationId)->value('unit_id');
            $registration = Registration::query()->whereKey($registrationId)->first();
        }

        if (! $registration && $registrationId) {
            $registration = Registration::query()->whereKey($registrationId)->first();
            $unitId ??= $registration?->unit_id;
        }

        $unitUuid = $unitId ? Unit::query()->whereKey($unitId)->value('uuid') : null;

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
                registrationUuid: $registration?->uuid,
                unitUuid: $unitUuid,
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
        return url("/pendaftar/status/{$registration->uuid}");
    }

    private function adminRegistrationUrl(Registration $registration): string
    {
        return url("/admin/registrations/{$registration->uuid}/edit");
    }
}
