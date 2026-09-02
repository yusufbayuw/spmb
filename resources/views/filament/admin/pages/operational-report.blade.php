<x-filament-panels::page>
    @php($summary = $this->summary())
    @php($rows = $this->rows())

    <div class="space-y-6">
        <form wire:submit="applyFilters" class="space-y-4">
            {{ $this->form }}
            <div class="flex flex-wrap justify-end gap-3">
                <x-filament::button type="button" color="gray" outlined wire:click="resetFilters">
                    Reset
                </x-filament::button>
                <x-filament::button type="submit" icon="heroicon-m-funnel">
                    Terapkan Filter
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    href="{{ $this->getExportUrl() }}"
                    color="success"
                    icon="heroicon-m-arrow-down-tray"
                >
                    Export XLSX
                </x-filament::button>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Total Pendaftaran', number_format($summary['total']), 'heroicon-o-users'],
                ['Aktif', number_format($summary['active']), 'heroicon-o-play-circle'],
                ['Selesai', number_format($summary['completed']), 'heroicon-o-check-circle'],
                ['Diterima', number_format($summary['accepted']), 'heroicon-o-academic-cap'],
            ] as [$label, $value, $icon])
                <x-filament::section compact>
                    <div class="flex items-center gap-4">
                        <div class="rounded-xl bg-primary-50 p-3 text-primary-600 dark:bg-primary-500/10">
                            <x-dynamic-component :component="$icon" class="h-6 w-6" />
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">{{ $label }}</div>
                            <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <x-filament::section>
                <x-slot name="heading">Pembayaran Terverifikasi</x-slot>
                <div class="text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['payment_verified']) }}</div>
            </x-filament::section>
            <x-filament::section>
                <x-slot name="heading">Potensi Biaya Pendaftaran</x-slot>
                <div class="text-3xl font-semibold text-gray-950 dark:text-white">Rp {{ number_format($summary['expected_fee'], 0, ',', '.') }}</div>
            </x-filament::section>
            <x-filament::section>
                <x-slot name="heading">Pembayaran Terverifikasi</x-slot>
                <div class="text-3xl font-semibold text-gray-950 dark:text-white">Rp {{ number_format($summary['verified_revenue'], 0, ',', '.') }}</div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Data Operasional</x-slot>
            <x-slot name="description">Preview maksimal 100 pendaftaran. Export XLSX memuat seluruh data yang sesuai filter.</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-white/10">
                        <tr>
                            <th class="px-3 py-3">No. Registrasi</th>
                            <th class="px-3 py-3">Calon Siswa</th>
                            <th class="px-3 py-3">Unit / Periode</th>
                            <th class="px-3 py-3">Biaya</th>
                            <th class="px-3 py-3">Lifecycle</th>
                            <th class="px-3 py-3">Tahap</th>
                            <th class="px-3 py-3">Pembayaran</th>
                            <th class="px-3 py-3">Seleksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($rows as $registration)
                            <tr>
                                <td class="px-3 py-3 font-mono text-xs">{{ $registration->registration_number }}</td>
                                <td class="px-3 py-3">
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $registration->full_name }}</div>
                                    <div class="text-xs text-gray-500">{{ $registration->nik }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <div>{{ $registration->unit?->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $registration->opening?->academic_year }} · {{ $registration->opening?->wave }} · {{ $registration->opening?->pathway }}</div>
                                </td>
                                <td class="px-3 py-3">Rp {{ number_format((float) ($registration->opening?->registration_fee ?? 0), 0, ',', '.') }}</td>
                                <td class="px-3 py-3">{{ $registration->lifecycleLabel() }}</td>
                                <td class="px-3 py-3">{{ $registration->stageLabel() }}</td>
                                <td class="px-3 py-3">{{ $registration->latestPayment?->status ?? '-' }}</td>
                                <td class="px-3 py-3">{{ $registration->selection?->decision ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-10 text-center text-gray-500">Tidak ada data sesuai filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
