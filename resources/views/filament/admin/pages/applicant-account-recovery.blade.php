<x-filament-panels::page>
    <x-filament::section
        heading="Bantuan akses sebelum pendaftaran"
        description="Hanya akun pendaftar tanpa data pendaftaran yang dapat ditangani di sini. Admin Unit hanya dapat menangani akun yang memang dikaitkan dengan unitnya; akun tanpa unit hanya untuk Super Admin."
    >
        <form wire:submit="sendRecovery" class="space-y-4">
            {{ $this->form }}
            <x-filament::button type="submit">Proses pemulihan</x-filament::button>
        </form>
    </x-filament::section>
    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Untuk akun yang sudah memiliki pendaftaran, gunakan menu Pendaftaran → Kirim Ulang Email.
            Petugas tidak menerima token, tidak dapat mengambil alih akun, dan tidak mengubah status verifikasi.
        </p>
    </x-filament::section>
</x-filament-panels::page>
