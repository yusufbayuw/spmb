<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Pilih kelompok peserta</x-slot>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
            Penugasan bersifat mandiri dari alur seleksi. Hanya akun pendaftar aktif dengan
            registrasi berstatus aktif dan sudah dikirim yang dapat dipilih. Pembukaan
            pendaftaran wajib dipilih agar tidak mencampur tahun ajaran, gelombang, atau prodi.
        </p>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <label class="block">
                <span class="text-sm font-medium">Paket Tes Online</span>
                <select wire:model.live="examId"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Pilih paket yang sudah terbit</option>
                    @foreach ($this->examOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-medium">Pembukaan Pendaftaran / Gelombang</span>
                <select wire:model.live="openingId"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Pilih pembukaan</option>
                    @foreach ($this->openingOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-medium">Jalur Pendaftaran (opsional)</span>
                <select wire:model.live="pathwayId"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Semua jalur pada pembukaan ini</option>
                    @foreach ($this->pathwayOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @error('selection')
            <p class="mt-4 text-sm font-medium text-danger-600">{{ $message }}</p>
        @enderror

        <div class="mt-5">
            <x-filament::button wire:click="preview" wire:loading.attr="disabled"
                :disabled="! $examId || ! $openingId" icon="heroicon-o-magnifying-glass">
                Pratinjau Peserta
            </x-filament::button>
        </div>
    </x-filament::section>

    @if (isset($previewData['signature']))
        <x-filament::section>
            <x-slot name="heading">Pratinjau Penugasan</x-slot>

            <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                <div><span class="block text-gray-500">Akun memenuhi syarat</span><strong>{{ $previewData['eligible'] }}</strong></div>
                <div><span class="block text-gray-500">Penugasan baru</span><strong>{{ $previewData['new'] }}</strong></div>
                <div><span class="block text-gray-500">Sudah ditugaskan</span><strong>{{ $previewData['already_assigned'] }}</strong></div>
                <div><span class="block text-gray-500">Akses pernah dicabut</span><strong>{{ $previewData['revoked'] }}</strong></div>
            </div>

            @if (! empty($previewData['sample']))
                <div class="mt-4">
                    <p class="mb-2 text-sm font-medium">Contoh daftar (maksimal 10 peserta)</p>
                    <ul class="list-disc space-y-1 pl-5 text-sm">
                        @foreach ($previewData['sample'] as $participant)
                            <li>{{ $participant['number'] ?: '-' }} · {{ $participant['name'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                Hanya penugasan baru yang dibuat. Akses yang telah dicabut tidak diaktifkan kembali.
                Sistem memeriksa ulang kelompok peserta saat tombol berikut ditekan dan akan
                menolak bila data berubah sejak pratinjau. Maksimal 500 akun per eksekusi.
            </p>

            <div class="mt-4">
                <x-filament::button wire:click="assign" wire:loading.attr="disabled"
                    color="primary" icon="heroicon-o-check-circle"
                    :disabled="($previewData['new'] ?? 0) === 0">
                    Konfirmasi Penugasan ({{ $previewData['new'] }} akun baru)
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
