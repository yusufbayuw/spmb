<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 sm:grid-cols-4">
            <div><p class="text-sm text-gray-500">Dalam antrean</p><strong class="text-2xl">{{ $queuedCount }}</strong></div>
            <div><p class="text-sm text-gray-500">Antrean lebih dari 15 menit</p><strong class="text-2xl text-warning-600">{{ $stuckCount }}</strong></div>
            <div><p class="text-sm text-gray-500">Gagal</p><strong class="text-2xl text-danger-600">{{ $failedCount }}</strong></div>
            <div><p class="text-sm text-gray-500">Diserahkan ke server (24 jam)</p><strong class="text-2xl">{{ $sentCount }}</strong></div>
        </div>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            Berhasil diserahkan ke server email bukan jaminan masuk inbox.
            Riwayat hanya tersedia sejak pencatatan pengiriman diaktifkan.
            Pengiriman ulang dilakukan pada daftar Pendaftaran dengan pemeriksaan kondisi bisnis terkini.
        </p>
    </x-filament::section>

    <x-filament::section heading="Riwayat dan status pengiriman">
        <div class="mb-4 flex flex-wrap items-end gap-4">
            <label class="text-sm">
                <span class="block mb-1">Status</span>
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-600">
                    <option value="all">Semua status</option>
                    <option value="queued">Dalam antrean</option>
                    <option value="sent">Diserahkan ke server email</option>
                    <option value="failed">Gagal</option>
                    <option value="skipped">Dilewati</option>
                </select>
            </label>
            <label class="text-sm">
                <span class="block mb-1">Jenis</span>
                <select wire:model.live="typeFilter" class="rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-600">
                    <option value="all">Semua jenis</option>
                    @foreach ($types as $key => $title)
                        <option value="{{ $key }}">{{ $title }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="border-b dark:border-gray-700">
                    <tr>
                        <th class="p-3">Waktu</th><th class="p-3">Pendaftar</th><th class="p-3">Jenis</th>
                        <th class="p-3">Penerima</th><th class="p-3">Status</th><th class="p-3">Percobaan</th>
                        <th class="p-3">Pemicu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attempts as $attempt)
                        <tr class="border-b dark:border-gray-700">
                            <td class="p-3 whitespace-nowrap">{{ $attempt->created_at->format('d/m/Y H:i') }}</td>
                            <td class="p-3">
                                @if ($attempt->registration)
                                    <a class="text-primary-600 underline" href="{{ url('/admin/registrations/'.$attempt->registration->uuid.'/edit') }}">
                                        {{ $attempt->registration->registration_number ?: $attempt->registration->full_name }}
                                    </a>
                                @else
                                    Akun / arsip
                                @endif
                            </td>
                            <td class="p-3">{{ $types[$attempt->type] ?? $attempt->type }}</td>
                            <td class="p-3">{{ $attempt->recipient_email }}</td>
                            <td class="p-3">
                                {{ match ($attempt->status) {
                                    'sent' => 'Diserahkan',
                                    'failed' => 'Gagal',
                                    'skipped' => 'Dilewati',
                                    default => 'Antrean'
                                } }}
                            </td>
                            <td class="p-3">{{ $attempt->attempt_count }}</td>
                            <td class="p-3">{{ $attempt->requester?->name ?: 'Otomatis' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4 text-center text-gray-500">Belum ada catatan pengiriman sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $attempts->links() }}</div>
    </x-filament::section>
</x-filament-panels::page>
