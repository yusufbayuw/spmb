<x-filament-panels::page>
    <div class="space-y-6">
        @if ($openings->isEmpty())
            <x-filament::section>
                <div class="py-10 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/5">
                        <x-heroicon-o-calendar-days class="h-7 w-7" />
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">Belum ada pendaftaran yang tersedia</h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                        Pembukaan SPMB sekolah maupun PMB perguruan tinggi akan tampil setelah dipublikasikan oleh petugas.
                    </p>
                </div>
            </x-filament::section>
        @else
            <div class="grid gap-5 lg:grid-cols-2">
                @foreach ($openings as $opening)
                    @php($isUniversity = $opening->unit?->isHigherEducation() ?? false)
                    <x-filament::section>
                        <x-slot name="heading">
                            {{ $opening->studyProgram?->label() ?? $opening->unit?->name }}
                        </x-slot>
                        <x-slot name="description">
                            {{ $isUniversity ? ($opening->unit?->name.' · Tahun Akademik '.$opening->academic_year) : ('Tahun Ajaran '.$opening->academic_year) }}
                        </x-slot>

                        <div class="space-y-5">
                            @if ($opening->studyProgram)
                                <div class="flex flex-wrap gap-2">
                                    <x-filament::badge color="info">{{ $opening->studyProgram->degree_level }}</x-filament::badge>
                                    @if ($opening->studyProgram->faculty)
                                        <x-filament::badge color="gray">{{ $opening->studyProgram->faculty }}</x-filament::badge>
                                    @endif
                                    @if ($opening->studyProgram->max_age)
                                        <x-filament::badge color="warning">Usia maks. {{ $opening->studyProgram->max_age }} tahun</x-filament::badge>
                                    @endif
                                </div>
                            @endif

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Gelombang</div>
                                    <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $opening->wave }}</div>
                                </div>
                                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Jalur</div>
                                    <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $opening->pathway }}</div>
                                </div>
                                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Biaya Formulir</div>
                                    <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $opening->formattedFee() }}</div>
                                </div>
                            </div>

                            @if ($opening->description)
                                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $opening->description }}</p>
                            @endif

                            <div class="flex items-center justify-between gap-4">
                                <x-filament::badge :color="$opening->isOpen() ? 'success' : 'warning'">
                                    {{ $opening->statusLabel() }}
                                </x-filament::badge>

                                @if ($opening->isOpen())
                                    <x-filament::button
                                        tag="a"
                                        href="{{ \App\Filament\Applicant\Resources\RegistrationResource::getUrl('create', ['opening' => $opening->id]) }}"
                                        icon="heroicon-m-arrow-right"
                                    >
                                        {{ $isUniversity ? 'Daftar sebagai Mahasiswa' : 'Daftar' }}
                                    </x-filament::button>
                                @else
                                    <x-filament::button disabled color="gray" icon="heroicon-m-lock-closed">
                                        Pendaftaran Ditutup
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
