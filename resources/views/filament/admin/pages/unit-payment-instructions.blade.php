<x-filament-panels::page>
    @if (count($this->units()) > 1)
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-sm font-medium text-gray-950 dark:text-white">
                Unit
                <select wire:model="unitUuid" class="mt-1 block rounded-lg border-gray-300 bg-white dark:border-white/10 dark:bg-gray-900">
                    @foreach ($this->units() as $uuid => $name)
                        <option value="{{ $uuid }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <x-filament::button wire:click="loadUnit" color="gray">Buka Unit</x-filament::button>
        </div>
    @endif

    @if ($unitUuid)
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}
            <x-filament::button type="submit">Simpan Petunjuk Pembayaran</x-filament::button>
        </form>
    @else
        <x-filament::section>Belum ada unit operasional yang tersedia.</x-filament::section>
    @endif
</x-filament-panels::page>
