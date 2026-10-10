<x-filament-panels::page>
    <x-filament::section heading="Keputusan kesiapan production">
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                @if ($report['status'] === 'ready_for_review')
                    <x-filament::badge color="success">Seluruh pemeriksaan lulus — perlu persetujuan rilis manusia</x-filament::badge>
                @else
                    <x-filament::badge color="danger">BELUM SIAP PRODUCTION</x-filament::badge>
                @endif
                <span class="text-sm">Environment: <strong>{{ $report['environment'] }}</strong></span>
                <span class="text-sm">Release SHA: <code>{{ $report['release_sha'] ?: 'BELUM DIATUR' }}</code></span>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border p-3 dark:border-gray-700"><p class="text-sm">Lulus</p><strong class="text-2xl">{{ $report['passes'] }}</strong></div>
                <div class="rounded-lg border p-3 dark:border-gray-700"><p class="text-sm">Gagal</p><strong class="text-2xl text-danger-600">{{ $report['failures'] }}</strong></div>
                <div class="rounded-lg border p-3 dark:border-gray-700"><p class="text-sm">Peringatan</p><strong class="text-2xl text-warning-600">{{ $report['warnings'] }}</strong></div>
                <div class="rounded-lg border p-3 dark:border-gray-700"><p class="text-sm">Manual belum dinilai</p><strong class="text-2xl">{{ $report['pending'] }}</strong></div>
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                Pemeriksaan otomatis bersifat read-only. Belum dapat membuktikan kualitas inbox email, keamanan TLS dari sisi pengguna,
                restore backup, push di perangkat nyata, atau audit penetrasi. Semua pemeriksaan manual harus disertai bukti.
                Bukti manual hanya berlaku untuk SHA rilis dan deployment yang sama.
            </p>

            <x-filament::button wire:click="saveSnapshot" icon="heroicon-o-document-check">
                Simpan snapshot audit saat ini
            </x-filament::button>
            <span class="text-xs text-gray-500">Snapshot tidak menerbitkan persetujuan production otomatis.</span>
        </div>
    </x-filament::section>

    <x-filament::section heading="Checklist otomatis dan manual">
        <div class="space-y-6">
            @foreach ($groups as $category => $items)
                <div>
                    <h3 class="font-semibold mb-2">{{ $category }}</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead><tr class="border-b dark:border-gray-700">
                                <th class="p-2">Pemeriksaan</th><th class="p-2">Status</th><th class="p-2">Temuan / langkah validasi</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="p-2 align-top font-medium">{{ $item['label'] }}
                                            @if ($item['manual']) <span class="text-xs text-gray-500">(manual)</span> @endif
                                        </td>
                                        <td class="p-2 align-top">
                                            @if ($item['status'] === 'pass') <x-filament::badge color="success">Lulus</x-filament::badge>
                                            @elseif ($item['status'] === 'fail') <x-filament::badge color="danger">Gagal</x-filament::badge>
                                            @elseif ($item['status'] === 'warning') <x-filament::badge color="warning">Peringatan</x-filament::badge>
                                            @else <x-filament::badge color="gray">Belum diverifikasi</x-filament::badge>
                                            @endif
                                        </td>
                                        <td class="p-2 align-top">
                                            <p>{{ $item['detail'] }}</p>
                                            @if ($item['manual'] && ! empty($item['evidence']))
                                                <p class="mt-1 text-xs text-gray-500">Bukti: {{ $item['evidence'] }}</p>
                                                <p class="text-xs text-gray-500">Ditinjau: {{ $item['reviewed_at'] }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Validasi manual — hanya Super Admin">
        <form wire:submit="saveAttestation" class="space-y-4">
            {{ $this->form }}
            <x-filament::button type="submit">Simpan bukti pemeriksaan</x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section heading="Riwayat snapshot audit">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead><tr class="border-b dark:border-gray-700">
                    <th class="p-2">Waktu</th><th class="p-2">SHA Rilis</th><th class="p-2">Status</th>
                    <th class="p-2">Gagal</th><th class="p-2">Peringatan</th><th class="p-2">Manual tertunda</th>
                </tr></thead>
                <tbody>
                    @forelse ($history as $item)
                        <tr class="border-b dark:border-gray-700">
                            <td class="p-2">{{ $item->created_at }}</td>
                            <td class="p-2"><code>{{ $item->release_sha ?: '-' }}</code></td>
                            <td class="p-2">{{ $item->status }}</td>
                            <td class="p-2">{{ $item->failed_count }}</td>
                            <td class="p-2">{{ $item->warning_count }}</td>
                            <td class="p-2">{{ $item->pending_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-3 text-gray-500">Belum ada snapshot audit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
