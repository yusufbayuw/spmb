<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Applicant\Pages\Dashboard::getUrl() }}"
                color="gray"
                outlined
            >
                Kembali
            </x-filament::button>

            <x-filament::button
                type="submit"
                icon="heroicon-m-paper-airplane"
            >
                Kirim Pendaftaran
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
