<x-filament-panels::page>
    @php
        $documents = $this->registrationRecord->documents->keyBy('type');
        $labels = [
            'report_card' => 'Rapor',
            'family_card' => 'Kartu Keluarga',
            'birth_certificate' => 'Akta Kelahiran',
            'photo' => 'Pas Foto',
        ];
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($labels as $type => $label)
                    @php($document = $documents->get($type))
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $label }}</div>
                        <div class="mt-2">
                            @if ($document?->is_verified)
                                <x-filament::badge color="success" icon="heroicon-m-check-circle">Terverifikasi</x-filament::badge>
                            @elseif ($document)
                                <x-filament::badge color="warning" icon="heroicon-m-clock">Menunggu verifikasi</x-filament::badge>
                            @else
                                <x-filament::badge color="gray">Belum diunggah</x-filament::badge>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <form wire:submit="submit" class="space-y-6">
            {{ $this->form }}

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Applicant\Pages\RegistrationStatus::getUrl(['registration' => $this->registrationRecord->id]) }}"
                    color="gray"
                    outlined
                >
                    Kembali
                </x-filament::button>
                <x-filament::button type="submit" icon="heroicon-m-cloud-arrow-up">
                    Simpan Dokumen
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
