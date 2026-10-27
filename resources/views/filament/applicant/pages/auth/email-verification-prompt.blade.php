<x-filament-panels::page.simple>
    <p class="text-center text-sm text-gray-600 dark:text-gray-300">
        Kami telah mengirim tautan verifikasi ke
        <strong class="break-all">{{ filament()->auth()->user()->getEmailForVerification() }}</strong>.
        Buka email tersebut, lalu klik <strong>Verifikasi Alamat Email</strong> untuk melanjutkan pendaftaran.
    </p>

    <x-auth.email-verification-help />

    <p class="text-center text-sm text-gray-600 dark:text-gray-300">
        Belum menemukan pesan? {{ $this->resendNotificationAction }}
    </p>
</x-filament-panels::page.simple>
