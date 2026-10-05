<?php

namespace App\Services;

use App\Filament\Applicant\Pages\DocumentsUpload;
use App\Filament\Applicant\Pages\PaymentUpload;
use App\Filament\Applicant\Pages\ReRegistration;
use App\Filament\Applicant\Pages\RegistrationStatus;
use App\Filament\Applicant\Pages\TestSchedule;
use App\Filament\Applicant\Resources\RegistrationResource;
use App\Models\Registration;
use Illuminate\Support\Collection;

class ApplicantNextActionService
{
    /**
     * Resolve the applicant-facing action for the registration's current stage.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(Registration $registration): ?array
    {
        if (! $registration->isOperational() || $registration->current_stage === 'completed') {
            return null;
        }

        if ($registration->data_validation_status === 'revision') {
            return $this->item(
                registration: $registration,
                state: 'attention',
                stateLabel: 'Perlu diperbaiki',
                title: 'Perbaiki Data Pendaftaran',
                message: $registration->data_validation_notes
                    ?: 'Petugas meminta perbaikan data pendaftaran sebelum proses dapat dilanjutkan.',
                color: 'warning',
                icon: 'heroicon-o-exclamation-triangle',
                actionLabel: 'Perbaiki Data',
                actionUrl: RegistrationResource::getUrl(
                    'edit',
                    ['record' => $registration],
                    panel: 'pendaftar',
                ),
                priority: 0,
            );
        }

        return match ($registration->current_stage) {
            'payment' => $this->item(
                $registration,
                'action_required',
                'Perlu tindakan',
                'Selesaikan Pembayaran',
                'Unggah bukti pembayaran agar proses pendaftaran dapat dilanjutkan.',
                'primary',
                'heroicon-o-banknotes',
                'Upload Bukti Pembayaran',
                PaymentUpload::getUrl(['registration' => $registration->uuid], panel: 'pendaftar'),
                10,
            ),
            'documents' => $this->item(
                $registration,
                'action_required',
                'Perlu tindakan',
                'Lengkapi Dokumen',
                'Lengkapi dokumen yang diwajibkan untuk melanjutkan proses pendaftaran.',
                'primary',
                'heroicon-o-document-arrow-up',
                'Lengkapi Dokumen',
                DocumentsUpload::getUrl(['registration' => $registration->uuid], panel: 'pendaftar'),
                10,
            ),
            'tests' => $this->item(
                $registration,
                'action_required',
                'Perlu tindakan',
                'Jadwal dan Pelaksanaan Tes',
                'Pilih atau periksa jadwal tes, lalu ikuti rangkaian tes sesuai ketentuan.',
                'primary',
                'heroicon-o-academic-cap',
                'Pilih / Ubah Jadwal Tes',
                TestSchedule::getUrl(['registration' => $registration->uuid], panel: 'pendaftar'),
                10,
            ),
            'admission_offer' => $this->item(
                $registration,
                'action_required',
                'Perlu konfirmasi',
                'Konfirmasi Penerimaan',
                'Hasil penerimaan telah tersedia. Tinjau hasil dan konfirmasikan keputusan penerimaan.',
                'success',
                'heroicon-o-academic-cap',
                'Lihat & Konfirmasi Penerimaan',
                RegistrationStatus::getUrl(['registration' => $registration->uuid], panel: 'pendaftar'),
                5,
            ),
            're_registration' => $this->item(
                $registration,
                'action_required',
                'Perlu tindakan',
                'Lanjutkan Daftar Ulang',
                'Lengkapi persyaratan daftar ulang agar proses penerimaan dapat dilanjutkan.',
                'primary',
                'heroicon-o-document-check',
                'Lanjutkan Daftar Ulang',
                ReRegistration::getUrl(['registration' => $registration->uuid], panel: 'pendaftar'),
                10,
            ),
            'document_verification' => $this->item(
                $registration,
                'processing',
                'Sedang diproses',
                'Verifikasi Dokumen',
                'Dokumen sudah dikirim dan sedang diperiksa oleh petugas.',
                'info',
                'heroicon-o-document-magnifying-glass',
                'Lihat Dokumen',
                DocumentsUpload::getUrl(['registration' => $registration->uuid], panel: 'pendaftar'),
                30,
            ),
            'data_validation' => $this->processing(
                $registration,
                'Validasi Data',
                'Data pendaftaran sudah dikirim. Menunggu validasi oleh petugas.',
                'heroicon-o-clock',
            ),
            'virtual_account' => $this->processing(
                $registration,
                'Virtual Account',
                'Menunggu Virtual Account diterbitkan oleh petugas.',
                'heroicon-o-envelope',
            ),
            'payment_verification' => $this->processing(
                $registration,
                'Verifikasi Pembayaran',
                'Bukti pembayaran sudah dikirim dan sedang diverifikasi oleh petugas.',
                'heroicon-o-clock',
            ),
            'applicant_card' => $this->processing(
                $registration,
                'Kartu Pendaftaran',
                'Menunggu penerbitan kartu pendaftaran oleh petugas.',
                'heroicon-o-identification',
            ),
            'selection' => $this->processing(
                $registration,
                'Proses Seleksi',
                'Seluruh data yang diperlukan sedang diproses untuk keputusan seleksi.',
                'heroicon-o-clock',
            ),
            'announcement' => $this->processing(
                $registration,
                'Pengumuman Hasil',
                'Menunggu hasil seleksi dipublikasikan.',
                'heroicon-o-megaphone',
            ),
            'waiting_list' => $this->processing(
                $registration,
                'Daftar Tunggu',
                'Pendaftaran berada dalam daftar tunggu. Pantau portal untuk perubahan status.',
                'heroicon-o-clock',
            ),
            'enrollment' => $this->processing(
                $registration,
                'Enrollment',
                'Menunggu proses enrollment diselesaikan oleh petugas.',
                'heroicon-o-user-plus',
            ),
            default => $this->processing(
                $registration,
                $registration->stageLabel(),
                'Tahap pendaftaran ini sedang berlangsung. Pantau portal untuk informasi berikutnya.',
                'heroicon-o-clock',
            ),
        };
    }

    /**
     * @param  iterable<Registration>  $registrations
     * @return Collection<int, array<string, mixed>>
     */
    public function resolveMany(iterable $registrations): Collection
    {
        return collect($registrations)
            ->map(fn (Registration $registration): ?array => $this->resolve($registration))
            ->filter()
            ->sortBy(fn (array $item): string => sprintf(
                '%03d-%s',
                $item['priority'],
                mb_strtolower($item['registration_name']),
            ))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function processing(
        Registration $registration,
        string $title,
        string $message,
        string $icon,
    ): array {
        return $this->item(
            $registration,
            'processing',
            'Sedang diproses',
            $title,
            $message,
            'info',
            $icon,
            null,
            null,
            30,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        Registration $registration,
        string $state,
        string $stateLabel,
        string $title,
        string $message,
        string $color,
        string $icon,
        ?string $actionLabel,
        ?string $actionUrl,
        int $priority,
    ): array {
        return [
            'registration_id' => $registration->getKey(),
            'registration_uuid' => $registration->uuid,
            'registration_name' => $registration->full_name,
            'registration_number' => $registration->registration_number,
            'unit_name' => $registration->unit?->name ?? 'Unit / Institusi',
            'stage' => $registration->current_stage,
            'stage_label' => $registration->stageLabel(),
            'state' => $state,
            'state_label' => $stateLabel,
            'title' => $title,
            'message' => $message,
            'color' => $color,
            'icon' => $icon,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'priority' => $priority,
        ];
    }
}
