<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3">
        <label class="text-sm font-medium text-gray-950 dark:text-white">
            Unit
            <select wire:model="unitUuid" class="mt-1 block rounded-lg border-gray-300 bg-white dark:border-white/10 dark:bg-gray-900">
                @foreach($this->units() as $uuid => $name)
                    <option value="{{ $uuid }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if($unitUuid)
        <x-filament::section heading="Ekspor konfigurasi">
            <div class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Ekspor menghasilkan satu berkas JSON portabel yang memuat profil penerimaan, informasi sebelum mendaftar,
                    FAQ, jalur pendaftaran, program studi, tes dan sesi tes, serta Pengaturan Pendaftaran Unit.
                    Data pendaftar, pembayaran, dokumen pendaftar, hasil seleksi, dan file media di storage tidak ikut diekspor.
                </p>
                <x-filament::button type="button" wire:click="export" icon="heroicon-o-arrow-down-tray">
                    Unduh Konfigurasi JSON
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section heading="Impor konfigurasi">
            <form wire:submit="import" class="space-y-5">
                {{ $this->importForm }}
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Impor akan menggabungkan profil, FAQ, jalur, program studi, dan tes ke unit tujuan.
                    Pengaturan Pendaftaran Unit tidak langsung dipublikasikan; sistem membuat atau memperbarui draft agar dapat diperiksa terlebih dahulu.
                </p>
                <x-filament::button
                    type="submit"
                    color="warning"
                    icon="heroicon-o-arrow-up-tray"
                    wire:confirm="Impor konfigurasi ke unit ini? Pastikan unit tujuan sudah benar. Pengaturan pendaftaran akan disimpan sebagai draft."
                >
                    Impor Konfigurasi
                </x-filament::button>
            </form>
        </x-filament::section>
    @endif
</x-filament-panels::page>
