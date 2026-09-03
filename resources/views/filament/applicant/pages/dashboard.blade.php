<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Pendaftaran Saya</h2>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                        Pilih pembukaan berdasarkan unit atau institusi, program studi bila ada, tahun ajaran/akademik, gelombang, dan jalur. Satu akun dapat menyimpan lebih dari satu pendaftaran.
                    </p>
                </div>

                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Applicant\Pages\RegistrationOpenings::getUrl() }}"
                    icon="heroicon-m-calendar-days"
                >
                    Pilih Pendaftaran
                </x-filament::button>
            </div>
        </x-filament::section>

        @if ($registrations->isEmpty())
            <x-filament::section>
                <div class="py-10 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10">
                        <x-heroicon-o-user-plus class="h-7 w-7" />
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">Belum ada pendaftaran</h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                        Pilih pembukaan SPMB sekolah atau PMB perguruan tinggi yang tersedia. Unit/institusi dan program studi akan mengikuti pilihan tersebut.
                    </p>
                    <div class="mt-5">
                        <x-filament::button
                            tag="a"
                            href="{{ \App\Filament\Applicant\Pages\RegistrationOpenings::getUrl() }}"
                            icon="heroicon-m-arrow-right"
                        >
                            Lihat Pendaftaran Tersedia
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @else
            <div class="grid gap-5 xl:grid-cols-2">
                @foreach ($registrations as $registration)
                    @php
                        $stages = array_keys(\App\Models\Registration::STAGES);
                        $stageIndex = array_search($registration->current_stage, $stages, true);
                        $stageIndex = $stageIndex === false ? 0 : $stageIndex;
                        $progress = (int) round((($stageIndex + 1) / count($stages)) * 100);
                        $isHigherEducation = $registration->unit?->isHigherEducation() ?? false;
                    @endphp

                    <x-filament::section>
                        <x-slot name="heading">{{ $registration->full_name }}</x-slot>
                        <x-slot name="description">
                            {{ $registration->registration_number }} · {{ $registration->unit?->name ?? 'Unit / institusi belum ditentukan' }}
                        </x-slot>

                        <div class="space-y-5">
                            @if ($registration->opening)
                                <div class="rounded-xl bg-gray-50 p-4 text-sm dark:bg-white/5">
                                    @if ($registration->opening->studyProgram)
                                        <div class="font-semibold text-gray-950 dark:text-white">
                                            {{ $registration->opening->studyProgram->label() }}
                                        </div>
                                    @endif
                                    <div class="{{ $registration->opening->studyProgram ? 'mt-1' : 'font-semibold text-gray-950 dark:text-white' }}">
                                        {{ $isHigherEducation ? 'Tahun Akademik' : 'Tahun Ajaran' }} {{ $registration->opening->academic_year }} · {{ $registration->opening->wave }}
                                    </div>
                                    <div class="mt-1 text-gray-500 dark:text-gray-400">
                                        Jalur {{ $registration->opening->pathway }}
                                    </div>
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::badge color="primary">{{ $registration->stageLabel() }}</x-filament::badge>
                                <x-filament::badge color="gray">
                                    {{ $registration->registrant_type === 'parent' ? 'Orang tua / wali' : ($isHigherEducation ? 'Calon mahasiswa' : 'Daftar mandiri') }}
                                </x-filament::badge>
                            </div>

                            @if ($registration->data_validation_status === 'revision')
                                <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
                                    <div class="font-semibold">Data perlu diperbaiki</div>
                                    @if ($registration->data_validation_notes)
                                        <div class="mt-1">{{ $registration->data_validation_notes }}</div>
                                    @endif
                                </div>
                            @endif

                            <div>
                                <div class="mb-2 flex items-center justify-between text-xs text-gray-500">
                                    <span>Progres Pendaftaran</span>
                                    <span class="font-medium">{{ $progress }}%</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                    <div class="h-full rounded-full bg-primary-600" style="width: {{ $progress }}%"></div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <x-filament::button
                                    tag="a"
                                    href="{{ \App\Filament\Applicant\Pages\RegistrationStatus::getUrl(['registration' => $registration->id]) }}"
                                    icon="heroicon-m-arrow-right-circle"
                                >
                                    Lihat Progres
                                </x-filament::button>

                                @if ($registration->current_stage === 'payment')
                                    <x-filament::button
                                        tag="a"
                                        href="{{ \App\Filament\Applicant\Pages\PaymentUpload::getUrl(['registration' => $registration->id]) }}"
                                        color="warning"
                                        icon="heroicon-m-banknotes"
                                    >
                                        Upload Pembayaran
                                    </x-filament::button>
                                @endif

                                @if (in_array($registration->current_stage, ['documents', 'document_verification'], true))
                                    <x-filament::button
                                        tag="a"
                                        href="{{ \App\Filament\Applicant\Pages\DocumentsUpload::getUrl(['registration' => $registration->id]) }}"
                                        color="info"
                                        icon="heroicon-m-document-arrow-up"
                                    >
                                        Dokumen
                                    </x-filament::button>
                                @endif

                                @if ($registration->applicant_card_number)
                                    <x-filament::button
                                        tag="a"
                                        href="{{ route('registration.card', $registration) }}"
                                        target="_blank"
                                        color="gray"
                                        outlined
                                        icon="heroicon-m-printer"
                                    >
                                        Cetak Kartu
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
