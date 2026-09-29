<?php

namespace App\Support;

final class SpmbOperationalMode
{
    public const K12 = 'K12';
    public const HIGHER_EDUCATION = 'HIGHER_EDUCATION';
    public const MIXED = 'MIXED';

    public static function mode(): string
    {
        $mode = mb_strtoupper(trim((string) config('spmb.operations.mode', self::MIXED)));

        return in_array($mode, [self::K12, self::HIGHER_EDUCATION, self::MIXED], true)
            ? $mode
            : self::MIXED;
    }

    public static function isK12(): bool { return self::mode() === self::K12; }
    public static function isHigherEducation(): bool { return self::mode() === self::HIGHER_EDUCATION; }
    public static function isMixed(): bool { return self::mode() === self::MIXED; }
    public static function allowsK12(): bool { return ! self::isHigherEducation(); }
    public static function allowsHigherEducation(): bool { return ! self::isK12(); }

    public static function allowedEducationCategories(): array
    {
        return match (self::mode()) {
            self::K12 => ['early_childhood', 'school'],
            self::HIGHER_EDUCATION => ['higher_education'],
            default => ['early_childhood', 'school', 'higher_education'],
        };
    }

    public static function allowedInstitutionTypes(): array
    {
        return match (self::mode()) {
            self::K12 => ['early_childhood', 'school'],
            self::HIGHER_EDUCATION => ['university'],
            default => ['early_childhood', 'school', 'university'],
        };
    }

    public static function allowsInstitutionType(?string $institutionType): bool
    {
        return filled($institutionType)
            && in_array($institutionType, self::allowedInstitutionTypes(), true);
    }

    public static function profile(): array
    {
        return match (self::mode()) {
            self::K12 => [
                'mode' => self::K12,
                'portal_label' => 'SPMB',
                'public_eyebrow' => 'Penerimaan Siswa Baru',
                'homepage_heading' => 'Pilih jenjang dan pembukaan pendaftaran',
                'homepage_intro' => 'Pilih jenjang pendidikan tujuan, lalu lihat pembukaan yang tersedia untuk memulai pendaftaran.',
                'page_description' => 'Portal penerimaan pendidikan untuk Daycare, KB, TK, SD, SMP, hingga SMA.',
                'levels_heading' => 'Jenjang pendidikan yang tersedia',
                'scope_heading' => 'Satu portal penerimaan dari PAUD hingga SMA.',
                'scope_description' => 'Portal ini mendukung penerimaan Daycare, KB, TK, SD, SMP, dan SMA dalam satu sistem.',
                'search_placeholder' => 'Cari unit, jenjang, atau gelombang...',
                'applicant_subheading' => 'Pilih jenjang pendidikan tujuan.',
            ],
            self::HIGHER_EDUCATION => [
                'mode' => self::HIGHER_EDUCATION,
                'portal_label' => 'PMB',
                'public_eyebrow' => 'Penerimaan Mahasiswa Baru',
                'homepage_heading' => 'Pilih jenjang dan program studi',
                'homepage_intro' => 'Pilih jenjang dan program studi tujuan, lalu lihat gelombang penerimaan yang tersedia.',
                'page_description' => 'Portal Penerimaan Mahasiswa Baru untuk program Diploma dan Sarjana.',
                'levels_heading' => 'Jenjang pendidikan tinggi yang tersedia',
                'scope_heading' => 'Satu portal Penerimaan Mahasiswa Baru.',
                'scope_description' => 'Portal ini mendukung penerimaan mahasiswa program Diploma dan Sarjana.',
                'search_placeholder' => 'Cari program studi, jenjang, atau gelombang...',
                'applicant_subheading' => 'Pilih jenjang dan program studi tujuan.',
            ],
            default => [
                'mode' => self::MIXED,
                'portal_label' => 'SPMB & PMB',
                'public_eyebrow' => 'Pendaftaran',
                'homepage_heading' => 'Pilih jenjang dan pembukaan pendaftaran',
                'homepage_intro' => 'Pilih jenjang pendidikan atau program studi tujuan, lalu lihat pembukaan yang tersedia untuk memulai pendaftaran.',
                'page_description' => 'Portal penerimaan dari Daycare hingga perguruan tinggi. Lihat pembukaan, jadwal, biaya, dan mulai pendaftaran secara daring.',
                'levels_heading' => 'Dari pendidikan anak usia dini hingga perguruan tinggi',
                'scope_heading' => 'Satu portal penerimaan untuk seluruh jenjang pendidikan.',
                'scope_description' => 'Portal ini mendukung penerimaan Daycare, KB, TK, SD, SMP, SMA, dan perguruan tinggi dalam satu sistem.',
                'search_placeholder' => 'Cari unit, program studi, atau gelombang...',
                'applicant_subheading' => 'Pilih jenjang pendidikan atau program studi tujuan.',
            ],
        };
    }
}
