<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section compact>
            <div class="space-y-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="min-w-0 flex-1">
                        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" wire:target="search">
                            <x-filament::input
                                type="search"
                                wire:model.live.debounce.300ms="search"
                                placeholder="{{ $operationalProfile['search_placeholder'] }}"
                            />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <x-filament::badge color="gray">
                            {{ number_format($openings->total(), 0, ',', '.') }} pembukaan
                        </x-filament::badge>

                        @if (filled($search) || filled($educationLevelCode) || $availability !== 'open')
                            <x-filament::button
                                color="gray"
                                outlined
                                size="sm"
                                icon="heroicon-m-x-mark"
                                wire:click="clearFilters"
                            >
                                Atur ulang
                            </x-filament::button>
                        @endif
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-4 dark:border-white/10">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <span class="w-16 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-200">Jenjang</span>
                        <div class="flex flex-wrap gap-2">
                            <x-filament::button
                                size="sm"
                                :color="blank($educationLevelCode) ? 'primary' : 'gray'"
                                :outlined="filled($educationLevelCode)"
                                wire:click="selectEducationLevel"
                            >
                                Semua
                            </x-filament::button>

                            @foreach ($educationLevelOptions as $code)
                                <x-filament::button
                                    size="sm"
                                    :color="$educationLevelCode === $code ? 'primary' : 'gray'"
                                    :outlined="$educationLevelCode !== $code"
                                    wire:click="selectEducationLevel('{{ $code }}')"
                                >
                                    {{ $code }}
                                </x-filament::button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <span class="w-16 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-200">Status</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([
                            'open' => 'Dibuka',
                            'scheduled' => 'Akan Dibuka',
                            'all' => 'Semua',
                        ] as $value => $label)
                            <x-filament::button
                                size="sm"
                                :color="$availability === $value ? 'primary' : 'gray'"
                                :outlined="$availability !== $value"
                                wire:click="selectAvailability('{{ $value }}')"
                            >
                                {{ $label }}
                            </x-filament::button>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-filament::section>

        <div wire:loading.class="opacity-60" wire:target="search, selectEducationLevel, selectAvailability, clearFilters">
            @if ($openings->isEmpty())
                <x-filament::section>
                    <div class="grid justify-items-center gap-3 py-8 text-center">
                        <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        <div class="grid gap-1">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Tidak ada pendaftaran yang cocok</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Ubah kata kunci, jenjang, atau status untuk melihat pembukaan lain.</p>
                        </div>
                        @if (filled($search) || filled($educationLevelCode) || $availability !== 'open')
                            <x-filament::button color="gray" outlined wire:click="clearFilters">Tampilkan yang sedang dibuka</x-filament::button>
                        @endif
                    </div>
                </x-filament::section>
            @else
                <div class="grid gap-4 xl:grid-cols-2">
                    @foreach ($openings as $opening)
                        @php
                            $isUniversity = $opening->unit?->isHigherEducation() ?? false;
                            $status = $opening->operationalStatus();
                            $importantDate = $status === 'scheduled' ? $opening->opened_at : $opening->closed_at;
                            $levelCode = $opening->studyProgram?->educationLevel?->code
                                ?? $opening->unit?->educationLevel?->code
                                ?? $opening->studyProgram?->degree_level
                                ?? $opening->unit?->code;
                            $title = $opening->studyProgram?->name ?? $opening->unit?->name;
                            $description = $isUniversity
                                ? $opening->unit?->name.' · Tahun Akademik '.$opening->academic_year
                                : 'Tahun Ajaran '.$opening->academic_year;
                        @endphp

                        <x-filament::section
                            compact
                            :heading="$title"
                            :description="$description"
                        >
                            <x-slot name="headerEnd">
                                <x-filament::badge :color="$opening->isOpen() ? 'success' : ($status === 'scheduled' ? 'info' : 'gray')">
                                    {{ $opening->statusLabel() }}
                                </x-filament::badge>
                            </x-slot>

                            <div class="grid gap-4">
                                <div class="flex flex-wrap gap-2">
                                    @if ($levelCode)
                                        <x-filament::badge color="info">{{ $levelCode }}</x-filament::badge>
                                    @endif

                                    @if ($opening->studyProgram?->faculty)
                                        <x-filament::badge color="gray">{{ $opening->studyProgram->faculty }}</x-filament::badge>
                                    @endif

                                    @if ($opening->studyProgram?->max_age)
                                        <x-filament::badge color="warning">Usia maks. {{ $opening->studyProgram->max_age }} tahun</x-filament::badge>
                                    @endif
                                </div>

                                <dl class="grid gap-3 sm:grid-cols-3">
                                    <div class="grid gap-1">
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Gelombang</dt>
                                        <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $opening->wave }}</dd>
                                    </div>
                                    <div class="grid gap-1">
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Biaya formulir</dt>
                                        <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ (float) $opening->registration_fee === 0.0 ? 'Gratis' : $opening->formattedFee() }}</dd>
                                    </div>
                                    <div class="grid gap-1">
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $status === 'scheduled' ? 'Dibuka' : ($status === 'open' ? 'Batas daftar' : 'Ditutup') }}</dt>
                                        <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $importantDate ? $importantDate->translatedFormat('d M Y, H:i').' WIB' : 'Mengikuti informasi unit' }}</dd>
                                    </div>
                                </dl>

                                @if ($opening->description)
                                    <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $opening->description }}</p>
                                @endif

                                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
                                    <a href="{{ route('admissions.show', $opening) }}" class="text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                                        Lihat detail
                                    </a>

                                    @if ($opening->isOpen())
                                        <x-filament::button tag="a" :href="route('admissions.apply', $opening)" icon="heroicon-m-arrow-right">
                                            Daftar
                                        </x-filament::button>
                                    @elseif ($status === 'scheduled')
                                        <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">Belum dapat didaftarkan</span>
                                    @else
                                        <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">Pendaftaran ditutup</span>
                                    @endif
                                </div>
                            </div>
                        </x-filament::section>
                    @endforeach
                </div>

                @if ($openings->hasPages())
                    <x-filament::pagination :paginator="$openings" />
                @endif
            @endif
        </div>
    </div>
</x-filament-panels::page>
