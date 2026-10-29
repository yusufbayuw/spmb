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

    private function applicantAudience(?bool $higherEducation): string
    {
        if ($higherEducation !== null) {
            return $higherEducation ? SpmbOperationalMode::HIGHER_EDUCATION : SpmbOperationalMode::K12;
        }

        return SpmbOperationalMode::mode();
    }

    public function applicantIdentityHeading(?bool $higherEducation = null): string
    {
        return match ($this->applicantAudience($higherEducation)) {
            SpmbOperationalMode::K12 => 'Identitas Calon Siswa',
            SpmbOperationalMode::HIGHER_EDUCATION => 'Identitas Calon Mahasiswa',
            default => 'Identitas Calon Siswa / Mahasiswa',
        };
    }

    public function applicantIdentityDescription(?bool $higherEducation = null): string
    {
        return match ($this->applicantAudience($higherEducation)) {
            SpmbOperationalMode::K12 => 'Gunakan identitas yang sama dengan dokumen resmi calon siswa.',
            SpmbOperationalMode::HIGHER_EDUCATION => 'Gunakan identitas yang sama dengan dokumen resmi calon mahasiswa.',
            default => 'Gunakan identitas yang sama dengan dokumen resmi calon siswa atau calon mahasiswa.',
        };
    }

    public function applicantParentDescription(?bool $higherEducation = null): string
    {
        $introduction = match ($this->applicantAudience($higherEducation)) {
            SpmbOperationalMode::K12 => 'Data orang tua diperlukan sebagai bagian identitas pendaftaran.',
            SpmbOperationalMode::HIGHER_EDUCATION => 'Data orang tua tetap diperlukan sebagai bagian identitas pendaftaran, termasuk ketika calon mahasiswa mendaftar sendiri.',
            default => 'Data orang tua diperlukan sebagai bagian identitas pendaftaran, termasuk ketika peserta mendaftar sendiri.',
        };

        return $introduction.' Data dari pendaftaran sebelumnya pada akun ini akan diisikan kembali bila tersedia.';
    }

    public function applicantChoiceDescription(?bool $higherEducation = null): string
    {
        return match ($this->applicantAudience($higherEducation)) {
            SpmbOperationalMode::K12 => 'Unit sekolah, periode, dan biaya formulir mengikuti pembukaan. Pilih jalur pendaftaran yang tersedia.',
            SpmbOperationalMode::HIGHER_EDUCATION => 'Institusi, program studi, periode, dan biaya formulir mengikuti pembukaan. Pilih jalur pendaftaran yang tersedia.',
            default => 'Unit/institusi, program studi jika tersedia, periode, dan biaya formulir mengikuti pembukaan. Pilih jalur pendaftaran yang tersedia.',
        };
    }

    public function applicantSelfOption(?bool $higherEducation = null): string
    {
        return match ($this->applicantAudience($higherEducation)) {
            SpmbOperationalMode::K12 => 'Calon Siswa Sendiri',
            SpmbOperationalMode::HIGHER_EDUCATION => 'Calon Mahasiswa Sendiri',
            default => 'Calon Peserta Sendiri',
        };
    }

    public function applicantRelationshipLabel(?bool $higherEducation = null): string
    {
        return match ($this->applicantAudience($higherEducation)) {
            SpmbOperationalMode::K12 => 'Hubungan dengan Calon Siswa',
            SpmbOperationalMode::HIGHER_EDUCATION => 'Hubungan dengan Calon Mahasiswa',
            default => 'Hubungan dengan Calon Peserta',
        };
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
