<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3">
        <label>Unit<select wire:model="unitUuid" class="block rounded-lg border-gray-300 dark:bg-gray-900">@foreach($this->units() as $uuid => $name)<option value="{{ $uuid }}">{{ $name }}</option>@endforeach</select></label>
        <x-filament::button wire:click="loadUnit">Buka Pengaturan</x-filament::button>
    </div>
    @if($configurationUuid)
        <div wire:poll.3s="autosaveDraft" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <div class="text-sm" role="status" aria-live="polite">{{ $autosaveStatus }}</div>
            <x-filament::button type="button" color="gray" wire:click="showPreview">Pratinjau Form Pendaftar</x-filament::button>
        </div>
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}
            <div class="flex flex-wrap gap-3 pt-2 sm:gap-4">
                <x-filament::button type="submit">Simpan Draft</x-filament::button>
                <x-filament::button type="button" wire:click="showPreview" color="gray">Pratinjau Form Pendaftar</x-filament::button>
                <x-filament::button type="button" wire:click="publish" wire:confirm="Simpan draft terbaru lalu publikasikan konfigurasi untuk pendaftar baru? Pendaftar lama tetap memakai versi sebelumnya sampai Anda memilih menerapkan versi terpublikasi ke pendaftar aktif." color="success">Publikasikan</x-filament::button>
                <x-filament::button
                    type="button"
                    wire:click="applyPublishedToActiveRegistrations"
                    wire:confirm="Terapkan versi TERPUBLIKASI terbaru ke pendaftar aktif yang masih aman diperbarui? Semua pengaturan versi (bukan hanya Tes) akan ikut diterapkan. Pendaftar yang sudah menjalani tes, masuk batch, atau memiliki keputusan/pengumuman akan dilewati."
                    color="warning"
                >
                    Terapkan ke Pendaftar Aktif
                </x-filament::button>
            </div>
        </form>
        <x-filament::modal id="registration-preview" width="7xl" slide-over="false">
            <x-slot name="heading">Pratinjau Form Pendaftar</x-slot>
            <x-slot name="description">Pratinjau draft, belum dipublikasikan. Tidak ada data pendaftaran yang disimpan.</x-slot>
            @if($preview)
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4">
                    <div class="flex gap-2">
                        <x-filament::button size="sm" color="{{ $previewDevice === 'desktop' ? 'primary' : 'gray' }}" wire:click="$set('previewDevice', 'desktop')">Desktop</x-filament::button>
                        <x-filament::button size="sm" color="{{ $previewDevice === 'mobile' ? 'primary' : 'gray' }}" wire:click="$set('previewDevice', 'mobile')">Mobile</x-filament::button>
                    </div>
                    @if($previewToken)
                        <a target="_blank" rel="noopener noreferrer" href="{{ \App\Filament\Admin\Pages\UnitRegistrationFormPreview::getUrl(['token' => $previewToken]) }}" class="text-sm font-medium text-primary-600 underline dark:text-primary-400">Buka di Halaman Baru</a>
                    @endif
                </div>
                <div class="mx-auto w-full transition-all {{ $previewDevice === 'mobile' ? 'max-w-sm' : 'max-w-6xl' }}">
                    {{ $this->previewForm }}
                </div>
            @endif
        </x-filament::modal>
    @endif
</x-filament-panels::page>
