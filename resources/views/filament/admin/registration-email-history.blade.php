<div class="space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-300">Riwayat mencakup pengiriman otomatis dan pengiriman ulang sejak fitur ini diaktifkan. Status berhasil berarti diserahkan ke layanan email, bukan jaminan masuk inbox.</p>
    @forelse ($attempts as $attempt)
        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <strong class="text-sm text-gray-950 dark:text-white">{{ \App\Models\MailDeliveryAttempt::TYPES[$attempt->type] ?? $attempt->type }}</strong>
                <x-filament::badge :color="match ($attempt->status) {
                    'sent' => 'success',
                    'failed' => 'danger',
                    'skipped' => 'gray',
                    default => 'warning',
                }">{{ match ($attempt->status) {
                    'sent' => 'Diserahkan ke server email',
                    'failed' => 'Gagal',
                    'skipped' => 'Dilewati (kondisi berubah)',
                    default => 'Antrean',
                } }}</x-filament::badge>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $attempt->created_at->format('d/m/Y H:i:s') }} ·
                {{ $attempt->origin === 'manual' ? 'Manual oleh '.($attempt->requester?->name ?? 'Petugas') : 'Otomatis' }} ·
                {{ $attempt->recipient_email }}
            </p>
            @if ($attempt->reason)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Alasan: {{ $attempt->reason }}</p>
            @endif
            @if ($attempt->status === 'failed')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">Pengiriman gagal. Silakan periksa audit dan antrean email.</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">Belum ada catatan pengiriman email dalam fitur ini.</p>
    @endforelse
</div>
