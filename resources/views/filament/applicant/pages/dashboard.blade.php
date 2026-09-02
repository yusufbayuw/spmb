<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Pendaftaran Calon Siswa</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Satu akun orang tua dapat mendaftarkan lebih dari satu calon siswa dan memantau seluruh proses dari portal ini.
                    </p>
                </div>

                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Applicant\Resources\RegistrationResource::getUrl('create') }}"
                    icon="heroicon-m-plus"
                >
                    Daftarkan Calon Siswa
                </x-filament::button>
            </div>
        </x-filament::section>

        @if ($registrations->isEmpty())
            <x-filament::section>
                <div class="py-8 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10">
                        <x-heroicon-o-user-plus class="h-6 w-6" />
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">Belum ada pendaftaran</h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                        Mulai dengan mengisi data calon siswa. Setelah tersimpan, validasi, pembayaran, dokumen, tes, seleksi, dan pengumuman dapat dipantau dari panel yang sama.
                    </p>
                    <div class="mt-5">
                        <x-filament::button tag="a" href="{{ \App\Filament\Applicant\Resources\RegistrationResource::getUrl('create') }}">
                            Mulai Pendaftaran
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @else
            <div class="grid gap-5 xl:grid-cols-2">
                @foreach ($registrations as $registration)
                    <x-filament::section>
                        <x-slot name="heading">
                            {{ $registration->full_name }}
                        </x-slot>

                        <x-slot name="description">
                            {{ $registration->registration_number }} · {{ $registration->unit?->name ?? 'Unit belum ditentukan' }}
                        </x-slot>

                        <div class="space-y-5">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Tahap Saat Ini</div>
                                    <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $registration->stageLabel() }}</div>
                                </div>
                                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Status</div>
                                    <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ str($registration->status)->replace('_', ' ')->title() }}</div>
                                </div>
                            </div>

                            @if ($registration->data_validation_status === 'revision' && $registration->data_validation_notes)
                                <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
                                    <div class="font-semibold">Data perlu diperbaiki</div>
                                    <div class="mt-1">{{ $registration->data_validation_notes }}</div>
                                </div>
                            @endif

                            <div class="flex flex-wrap gap-2">
                                <x-filament::button
                                    tag="a"
                                    href="{{ \App\Filament\Applicant\Resources\RegistrationResource::getUrl('index') }}"
                                    icon="heroicon-m-arrow-right"
                                >
                                    Kelola Pendaftaran
                                </x-filament::button>

                                @if ($registration->applicant_card_number)
                                    <x-filament::button
                                        tag="a"
                                        href="{{ route('registration.card', $registration) }}"
                                        color="gray"
                                        icon="heroicon-m-identification"
                                    >
                                        Kartu Pendaftar
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
