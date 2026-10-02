<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Deployment Aman</x-slot>
        <div class="text-sm leading-6 text-gray-600 dark:text-gray-300">
            Mode enforcement default adalah <strong>Nonaktif</strong>. Aktifkan Peringatan terlebih dahulu sebelum mewajibkan sertifikasi untuk operasi sensitif atau seluruh mutasi role.
        </div>
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">Simpan Governance</x-filament::button>
    </form>
</x-filament-panels::page>
