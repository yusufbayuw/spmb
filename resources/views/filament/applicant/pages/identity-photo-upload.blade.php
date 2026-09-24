<x-filament-panels::page>
    @php
        $photo = app(\App\Services\RegistrationCardService::class)->identityPhoto($this->registrationRecord);
    @endphp

    @if($photo)
        <x-filament::section>
            <x-slot name="heading">Foto Saat Ini</x-slot>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <img
                    src="{{ route('files.applicant.documents.show', $photo) }}"
                    alt="Foto identitas {{ $this->registrationRecord->full_name }}"
                    class="h-40 w-32 rounded-xl border border-gray-200 object-cover shadow-sm dark:border-white/10"
                >
                <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <div>{{ $photo->displayFileName() }}</div>
                    @if($photo->is_verified)
                        <x-filament::badge color="success">Terverifikasi</x-filament::badge>
                    @else
                        <x-filament::badge color="warning">Belum diverifikasi</x-filament::badge>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endif

    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            @if(!$photo?->is_verified)
                <x-filament::button type="submit" icon="heroicon-m-photo">
                    Simpan Foto Identitas
                </x-filament::button>
            @endif

            <x-filament::button
                tag="a"
                :href="\App\Filament\Applicant\Pages\RegistrationStatus::getUrl(['registration' => $this->registrationRecord->uuid])"
                color="gray"
            >
                Kembali
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
