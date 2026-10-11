<?php

namespace App\Services;

use App\Support\SpmbOperationalMode;

/**
 * User-facing admission vocabulary. Internal SPMB identifiers and historical
 * records must not be rewritten when SPMB_MODE_OPS changes.
 */
final class AdmissionTerminology
{
    public function portalName(): string
    {
        return SpmbOperationalMode::profile()['portal_label'];
    }

    public function registrationTitle(): string
    {
        return match (SpmbOperationalMode::mode()) {
            SpmbOperationalMode::K12 => 'Pendaftaran Murid Baru',
            SpmbOperationalMode::HIGHER_EDUCATION => 'Pendaftaran Mahasiswa Baru',
            default => 'Pendaftaran Peserta Baru',
        };
    }

    public function announcementTitle(): string
    {
        return 'Pengumuman Hasil '.$this->portalName();
    }

    public function announcementAvailableTitle(): string
    {
        return 'Pengumuman hasil '.$this->portalName().' tersedia';
    }

    /**
     * Migrate only recognized *display defaults*, never custom titles or
     * persisted announcement/audit fields.
     */
    public function announcementDisplayTitle(?string $title): string
    {
        $title = trim((string) $title);

        return $title === '' || in_array(mb_strtolower($title), [
            'pengumuman spmb',
            'pengumuman pmb',
            'pengumuman spmb & pmb',
            'pengumuman hasil spmb',
            'pengumuman hasil pmb',
            'pengumuman hasil spmb & pmb',
        ], true)
            ? $this->announcementTitle()
            : $title;
    }

    public function notificationDisplayTitle(string $title): string
    {
        $value = mb_strtolower(trim($title));

        return in_array($value, [
            'pengumuman hasil spmb tersedia',
            'pengumuman hasil pmb tersedia',
            'pengumuman hasil spmb & pmb tersedia',
        ], true) ? $this->announcementAvailableTitle() : $this->announcementDisplayTitle($title);
    }

    public function virtualAccountSubject(): string
    {
        return 'Virtual Account Pendaftaran '.$this->portalName();
    }

    public function dashboardButton(): string
    {
        return 'Buka Dashboard '.$this->portalName();
    }

    public function committee(): string
    {
        return 'Panitia '.$this->portalName();
    }

    public function configurationNavigation(): string
    {
        return 'Konfigurasi '.$this->portalName();
    }

    public function paymentDescription(): string
    {
        return 'Biaya pendaftaran '.$this->portalName();
    }

    public function documentFooter(): string
    {
        return 'Dokumen diterbitkan melalui sistem '.$this->portalName().'. Simpan dokumen ini untuk keperluan pendaftaran.';
    }

    public function notificationFallback(): string
    {
        return 'Ada pembaruan pada proses '.$this->portalName().'.';
    }

    public function enrollmentNotice(): string
    {
        return 'Alamat email akun telah terverifikasi. Anda dapat melanjutkan proses '.$this->portalName().'.';
    }

    public function applicantOpeningDescription(): string
    {
        return match (SpmbOperationalMode::mode()) {
            SpmbOperationalMode::K12 => 'Pilih pembukaan SPMB sekolah yang tersedia. Unit dan jenjang akan mengikuti pilihan tersebut.',
            SpmbOperationalMode::HIGHER_EDUCATION => 'Pilih pembukaan PMB perguruan tinggi yang tersedia. Institusi dan program studi akan mengikuti pilihan tersebut.',
            default => 'Pilih pembukaan SPMB sekolah atau PMB perguruan tinggi yang tersedia. Unit/institusi dan program studi akan mengikuti pilihan tersebut.',
        };
    }
}
