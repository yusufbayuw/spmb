<x-filament-panels::page>
    @php($report = $this->data())
    @php($section = $this::analyticsSection())

    <div class="space-y-6">
        <form wire:submit="applyFilters" class="space-y-3">
            {{ $this->form }}

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Semua hasil mengikuti izin unit dan filter di atas. Data ditampilkan saat halaman dimuat atau filter diterapkan.
                </p>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button type="button" wire:click="resetFilters" color="gray" outlined icon="heroicon-m-arrow-path">
                        Reset Filter
                    </x-filament::button>
                    <x-filament::button type="submit" icon="heroicon-m-funnel">
                        Terapkan Filter
                    </x-filament::button>
                </div>
            </div>
        </form>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($report['stats'] as $stat)
                <x-filament::section compact>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ $stat['label'] }}
                        </div>
                        <div class="break-words text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
                            {{ $stat['value'] }}
                        </div>
                        @if ($stat['description'] !== '')
                            <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                                {{ $stat['description'] }}
                            </p>
                        @endif
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section>
            <x-slot name="heading">Visualisasi ringkas</x-slot>
            <x-slot name="description">Distribusi dari kelompok analisis utama sesuai filter yang aktif.</x-slot>
            @livewire(
                \App\Filament\Admin\Support\AnalyticsBreakdownChart::class,
                ['filters' => $this->filters ?? [], 'section' => $section],
                key('analytics-chart-'.$section.'-'.md5(json_encode($this->filters ?? [])))
            )
        </x-filament::section>

        <div class="grid gap-4 xl:grid-cols-2">
            @foreach ($report['groups'] as $group)
                <x-filament::section>
                    <x-slot name="heading">{{ $group['title'] }}</x-slot>
                    @if ($group['description'] !== '')
                        <x-slot name="description">{{ $group['description'] }}</x-slot>
                    @endif
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs font-semibold uppercase text-gray-500 dark:border-white/10 dark:text-gray-400">
                                    <th scope="col" class="px-2 py-3">Kategori</th>
                                    <th scope="col" class="px-2 py-3 text-right">Jumlah</th>
                                    <th scope="col" class="px-2 py-3 text-right">Porsi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @forelse ($group['rows'] as $row)
                                    <tr>
                                        <th scope="row" class="max-w-xs break-words px-2 py-2 font-normal text-gray-700 dark:text-gray-200">
                                            {{ $row['label'] }}
                                        </th>
                                        <td class="whitespace-nowrap px-2 py-2 text-right font-medium tabular-nums text-gray-950 dark:text-white">
                                            {{ number_format($row['count'], 0, ',', '.') }}
                                        </td>
                                        <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                            {{ number_format($row['percent'], 1, ',', '.') }}%
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-2 py-7 text-center text-gray-500">
                                            Belum ada data dalam cakupan filter ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section>
            <x-slot name="heading">Cara membaca hasil</x-slot>
            <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                @foreach ($report['notes'] as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
            <div class="mt-5 flex flex-wrap gap-2">
                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Admin\Resources\RegistrationResource::getUrl('index') }}"
                    color="gray"
                    outlined
                    icon="heroicon-m-users"
                >
                    Buka Data Pendaftar
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Admin\Pages\OperationalReport::getUrl() }}"
                    color="gray"
                    outlined
                    icon="heroicon-m-arrow-down-tray"
                >
                    Laporan Operasional &amp; XLSX
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
