<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3">
        <label>Unit<select wire:model="unitUuid" class="block rounded-lg border-gray-300 dark:bg-gray-900">@foreach($this->units() as $uuid => $name)<option value="{{ $uuid }}">{{ $name }}</option>@endforeach</select></label>
        <x-filament::button wire:click="loadUnit">Buka Pengaturan</x-filament::button>
    </div>
    @if($configurationUuid)
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}
            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit">Simpan Draft</x-filament::button>
                <x-filament::button wire:click="showPreview" color="gray">Pratinjau Form Pendaftar</x-filament::button>
                <x-filament::button wire:click="save(true)" wire:confirm="Publikasikan konfigurasi untuk pendaftar baru? Pendaftar lama tetap memakai versi sebelumnya sampai Anda memilih menerapkan versi terpublikasi ke pendaftar aktif." color="success">Publikasikan</x-filament::button>
                <x-filament::button
                    wire:click="applyPublishedToActiveRegistrations"
                    wire:confirm="Terapkan versi TERPUBLIKASI terbaru ke pendaftar aktif yang masih aman diperbarui? Semua pengaturan versi (bukan hanya Tes) akan ikut diterapkan. Pendaftar yang sudah menjalani tes, masuk batch, atau memiliki keputusan/pengumuman akan dilewati."
                    color="warning"
                >
                    Terapkan ke Pendaftar Aktif
                </x-filament::button>
            </div>
        </form>
        @if($preview)<x-filament::section heading="Pratinjau Form Pendaftar">{{ $this->previewForm }}</x-filament::section>@endif
    @endif
</x-filament-panels::page>
