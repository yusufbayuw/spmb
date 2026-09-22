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
        <x-filament::button wire:click="loadUnit" color="gray">Buka Profil</x-filament::button>
    </div>

    @if($unitUuid)
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}
            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit">Simpan Informasi Publik</x-filament::button>
            </div>
        </form>
    @endif
</x-filament-panels::page>
