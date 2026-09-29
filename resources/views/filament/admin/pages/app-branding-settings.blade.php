<x-filament-panels::page>
    <x-filament::section>
        <div class="text-sm leading-6 text-gray-600 dark:text-gray-300">
            Sebelum konfigurasi ini disimpan, aplikasi menggunakan nilai dari <code>.env</code>.
            Setelah disimpan, pengaturan di halaman ini menjadi override global tanpa mengubah data unit maupun pendaftaran existing.
        </div>
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">Simpan White-label</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
