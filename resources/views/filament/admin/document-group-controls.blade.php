{{-- Table-scoped controls: Filament's Alpine "table" owns collapsedGroups. --}}
<div class="flex flex-wrap items-center gap-2 px-4 py-3 sm:px-6">
    <span class="me-1 text-sm font-medium text-gray-700 dark:text-gray-200">
        Default tampilan accordion:
    </span>

    <x-filament::button
        type="button"
        size="sm"
        :color="$collapsed ? 'primary' : 'gray'"
        icon="heroicon-o-chevron-down"
        aria-pressed="{{ $collapsed ? 'true' : 'false' }}"
        wire:click="setDefaultGroupCollapsed(true)"
        x-on:click="collapsedGroups = Array.from($el.closest('.fi-ta').querySelectorAll('[data-spmb-group-title]')).map((el) => el.dataset.spmbGroupTitle)"
    >
        Tutup Semua
    </x-filament::button>

    <x-filament::button
        type="button"
        size="sm"
        :color="! $collapsed ? 'primary' : 'gray'"
        icon="heroicon-o-chevron-up"
        aria-pressed="{{ ! $collapsed ? 'true' : 'false' }}"
        wire:click="setDefaultGroupCollapsed(false)"
        x-on:click="collapsedGroups = []"
    >
        Buka Semua
    </x-filament::button>

    <span class="text-xs text-gray-500 dark:text-gray-400">
        Pilihan accordion diingat selama sesi. Pagination dihitung per pendaftar, bukan per berkas; tab status dan badge tetap menghitung berkas.
    </span>
</div>
