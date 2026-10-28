<x-filament-panels::page>
    <div wire:poll.15s="refresh">
        <x-filament::section>
            <x-slot name="heading">Pemantauan Ujian</x-slot>
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Data diperbarui otomatis setiap 15 detik selama halaman terbuka. Status "Sedang Mengerjakan"
                berarti percobaan masih aktif menurut server, bukan bukti peserta terhubung ke internet.
            </p>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium">Paket Tes</span>
                    <select wire:model.live="examId"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">Pilih paket ujian</option>
                        @foreach ($this->examOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="flex items-end">
                    <x-filament::button wire:click="refresh" icon="heroicon-o-arrow-path"
                        color="gray">Segarkan Sekarang</x-filament::button>
                </div>
            </div>
        </x-filament::section>

        @if ($snapshot)
            <x-filament::section>
                <x-slot name="heading">{{ $snapshot['exam'] }}</x-slot>
                <x-slot name="description">Pengambilan data: {{ $snapshot['as_of'] }}</x-slot>

                @php
                    $labels = [
                        'total' => 'Total Penugasan',
                        'not_started' => 'Belum Mulai',
                        'working' => 'Sedang Mengerjakan',
                        'overdue' => 'Melewati Batas Waktu',
                        'review' => 'Perlu Koreksi',
                        'finished' => 'Selesai',
                        'revoked' => 'Akses Dicabut',
                    ];
                @endphp

                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    @foreach ($labels as $key => $label)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs text-gray-600 dark:text-gray-300">{{ $label }}</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $snapshot['stats'][$key] }}</p>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-xs text-gray-500">
                    "Melewati Batas Waktu" berarti percobaan melewati deadline tetapi belum
                    difinalisasi oleh scheduler. "Selesai" mengikuti status tersimpan;
                    "Perlu Koreksi" ditampilkan terpisah.
                </p>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Daftar Peserta</x-slot>

                <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium">Cari nama / email</span>
                        <input type="search" wire:model.live.debounce.450ms="search"
                            maxlength="100" placeholder="Nama atau email peserta"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Filter Status</span>
                        <select wire:model.live="statusFilter"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="all">Semua Status</option>
                            @foreach (array_diff_key($labels, ['total' => true]) as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-max text-left text-sm">
                        <thead class="border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-3 py-2">Peserta</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Percobaan</th>
                                <th class="px-3 py-2">Terjawab</th>
                                <th class="px-3 py-2">Sisa Waktu</th>
                                <th class="px-3 py-2">Mulai</th>
                                <th class="px-3 py-2">Selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($snapshot['rows'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2">
                                        <span class="block font-medium">{{ $row['user_name'] }}</span>
                                        <span class="block text-xs text-gray-500">{{ $row['email'] }}</span>
                                    </td>
                                    <td class="px-3 py-2">{{ $labels[$row['status']] ?? 'Tidak Diketahui' }}</td>
                                    <td class="px-3 py-2">{{ $row['attempt_number'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $row['questions'] ? $row['answered'].'/'.$row['questions'] : '—' }}</td>
                                    <td class="px-3 py-2 font-mono tabular-nums">{{ $row['time_left'] }}</td>
                                    <td class="px-3 py-2">{{ $row['started_at'] }}</td>
                                    <td class="px-3 py-2">{{ $row['submitted_at'] }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-3 py-5 text-center text-gray-500" colspan="7">
                                    Tidak ada peserta sesuai filter.
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span>{{ $snapshot['total_rows'] }} peserta dalam filter ini ·
                        Halaman {{ $snapshot['page'] }} dari {{ $snapshot['last_page'] }}</span>
                    <div class="flex items-center gap-2">
                        <x-filament::button color="gray" wire:click="previousPage"
                            :disabled="$snapshot['page'] <= 1">Sebelumnya</x-filament::button>
                        <x-filament::button color="gray" wire:click="nextPage"
                            :disabled="$snapshot['page'] >= $snapshot['last_page']">Berikutnya</x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
