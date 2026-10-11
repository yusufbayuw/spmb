<?php

namespace App\Services;

use App\Models\AppSetting;
use Throwable;

class HomepageSectionService
{
    public const LABELS = [
        'registration' => 'Pendaftaran yang Dibuka',
        'my_registrations' => 'Pendaftaran Saya',
        'upcoming' => 'Jadwal Mendatang',
        'tutorials' => 'Video Tutorial',
        'steps' => 'Cara Mendaftar',
        'preparation' => 'Persiapan Pendaftaran',
        'scope' => 'Tentang Penerimaan',
        'helpdesk' => 'Bantuan & Helpdesk',
    ];

    public const DESCRIPTIONS = [
        'registration' => 'Daftar pembukaan aktif dan filter jenjang. Sembunyikan hanya bila memang diperlukan.',
        'my_registrations' => 'Ringkasan pendaftaran bagi pengguna yang sedang masuk.',
        'upcoming' => 'Pembukaan pendaftaran yang akan datang.',
        'tutorials' => 'Video tutorial dari unit pendidikan.',
        'steps' => 'Empat langkah cara mendaftar.',
        'preparation' => 'Hal-hal yang perlu disiapkan sebelum mendaftar.',
        'scope' => 'Penjelasan cakupan penerimaan dan tautan institusi.',
        'helpdesk' => 'Kanal bantuan dan kontak panitia.',
    ];

    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return array_fill_keys(array_keys(self::LABELS), true);
    }

    /** @return array<string, bool> */
    public static function normalize(mixed $blocks): array
    {
        $normalized = self::defaults();

        if (! is_array($blocks)) {
            return $normalized;
        }

        foreach (array_keys(self::LABELS) as $key) {
            if (array_key_exists($key, $blocks) && is_bool($blocks[$key])) {
                $normalized[$key] = $blocks[$key];
            }
        }

        return $normalized;
    }

    /** @return array<string, bool> */
    public function visibility(): array
    {
        try {
            return self::normalize(AppSetting::query()->first()?->homepage_blocks);
        } catch (Throwable) {
            // Preserve existing homepage during setup / pending migrations.
            return self::defaults();
        }
    }
}
