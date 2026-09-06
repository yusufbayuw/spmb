<x-filament-panels::page>
    <x-filament::tabs label="Filter status sesi tes">
        <x-filament::tabs.item :active="$statusTab === 'all'" wire:click="selectStatusTab('all')">
            Semua
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="$statusTab === 'active'" wire:click="selectStatusTab('active')">
            Aktif
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="$statusTab === 'closed'" wire:click="selectStatusTab('closed')">
            Ditutup
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="$statusTab === 'cancelled'" wire:click="selectStatusTab('cancelled')">
            Dibatalkan
        </x-filament::tabs.item>
    </x-filament::tabs>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
