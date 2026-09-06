<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section
            compact
            icon="heroicon-o-magnifying-glass"
            heading="Temukan pendaftaran"
            description="Cari berdasarkan unit, program studi, fakultas, tahun ajaran, atau gelombang."
        >
            <x-slot name="headerEnd">
                <x-filament::badge color="gray">
                    {{ number_format($openings->total(), 0, ',', '.') }} pembukaan
                </x-filament::badge>
            </x-slot>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(12rem,0.7fr)_auto]">
                <label class="grid gap-1.5">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Cari</span>
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" wire:target="search">
                        <x-filament::input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Contoh: Informatika, SMA, Gelombang 1"
                        />
                    </x-filament::input.wrapper>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Status</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="availability">
                            <option value="all">Semua status</option>
                            <option value="open">Sedang dibuka</option>
                            <option value="scheduled">Akan dibuka</option>
                            <option value="closed">Sudah ditutup</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                @if (filled($search) || filled($unitUuid) || $availability !== 'all')
                    <div class="flex items-end">
                        <x-filament::button color="gray" outlined icon="heroicon-m-x-mark" wire:click="clearFilters">
                            Atur ulang
                        </x-filament::button>
                    </div>
                @endif
            </div>

            <div class="grid gap-1.5">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Unit</span>
                <x-filament::tabs label="Filter unit pendaftaran">
                    <x-filament::tabs.item :active="blank($unitUuid)" icon="heroicon-m-squares-2x2" wire:click="selectUnit">
                        Semua unit
                    </x-filament::tabs.item>
                    @foreach ($unitOptions as $uuid => $name)
                        <x-filament::tabs.item :active="$unitUuid === $uuid" wire:click="selectUnit('{{ $uuid }}')">
                            {{ $name }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            </div>
        </x-filament::section>

        <div wire:loading.class="opacity-60" wire:target="search, unitUuid, availability">
            @if ($openings->isEmpty())
                <x-filament::section>
                    <div class="grid justify-items-center gap-3 py-8 text-center">
                        <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        <div class="grid gap-1">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Tidak ada pendaftaran yang cocok</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Ubah kata kunci atau filter untuk melihat pembukaan lain.</p>
                        </div>
                        @if (filled($search) || filled($unitUuid) || $availability !== 'all')
                            <x-filament::button color="gray" outlined wire:click="clearFilters">Tampilkan semua</x-filament::button>
                        @endif
                    </div>
                </x-filament::section>
            @else
                <div class="grid gap-4 xl:grid-cols-2">
                    @foreach ($openings as $opening)
                        @php($isUniversity = $opening->unit?->isHigherEducation() ?? false)
                        <x-filament::section
                            compact
                            :heading="$opening->studyProgram?->label() ?? $opening->unit?->name"
                            :description="$isUniversity ? $opening->unit?->name.' · Tahun Akademik '.$opening->academic_year : 'Tahun Ajaran '.$opening->academic_year"
                        >
                            <x-slot name="headerEnd">
                                <x-filament::badge :color="$opening->isOpen() ? 'success' : 'warning'">
                                    {{ $opening->statusLabel() }}
                                </x-filament::badge>
                            </x-slot>

                            <div class="grid gap-4">
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

                                <dl class="grid gap-3 sm:grid-cols-2">
                                    <div class="grid gap-1">
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Gelombang</dt>
                                        <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $opening->wave }}</dd>
                                    </div>
                                    <div class="grid gap-1">
                                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Biaya formulir</dt>
                                        <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $opening->formattedFee() }}</dd>
                                    </div>
                                </dl>

                                @if ($opening->description)
                                    <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $opening->description }}</p>
                                @endif

                                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        @if ($opening->opened_at && $opening->closed_at)
                                            {{ $opening->opened_at->format('d M Y H:i') }}–{{ $opening->closed_at->format('d M Y H:i') }}
                                        @else
                                            Periode ditentukan oleh unit
                                        @endif
                                    </p>

                                    @if ($opening->isOpen())
                                        <x-filament::button
                                            tag="a"
                                            :href="\App\Filament\Applicant\Resources\RegistrationResource::getUrl('create', ['opening' => $opening->uuid])"
                                            icon="heroicon-m-arrow-right"
                                        >
                                            {{ $isUniversity ? 'Pilih program' : 'Pilih pendaftaran' }}
                                        </x-filament::button>
                                    @else
                                        <x-filament::button disabled color="gray" icon="heroicon-m-lock-closed">
                                            {{ $opening->operationalStatus() === 'scheduled' ? 'Belum dibuka' : 'Ditutup' }}
                                        </x-filament::button>
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
