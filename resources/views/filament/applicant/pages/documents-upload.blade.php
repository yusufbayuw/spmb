<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div class="flex gap-3">
            <x-filament::button type="submit">Simpan Dokumen</x-filament::button>
            <x-filament::button
                tag="a"
                :href="\App\Filament\Applicant\Pages\RegistrationStatus::getUrl(['registration' => $registrationRecord->uuid])"
                color="gray"
            >
                Kembali
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
