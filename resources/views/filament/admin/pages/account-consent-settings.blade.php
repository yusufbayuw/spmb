<x-filament-panels::page>
    <x-filament::section>
        <div class="text-sm text-gray-600 dark:text-gray-300">
            Versi aktif saat ini: <strong>v{{ $this->publishedVersion() }}</strong>.
            Perubahan disimpan sebagai draft baru dan baru berlaku pada pembuatan akun setelah dipublikasikan.
        </div>
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">Simpan Draft</x-filament::button>
            <x-filament::button
                type="button"
                wire:click="publish"
                wire:confirm="Publikasikan versi kebijakan akun ini? Pendaftar baru akan wajib menyetujui versi terbaru."
                color="success"
            >
                Publikasikan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
