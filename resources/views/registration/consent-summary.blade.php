@if (! $consent)
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
        Persetujuan belum tercatat pada pendaftaran ini. Data pendaftaran lama yang dibuat sebelum fitur persetujuan tersedia tetap dipertahankan apa adanya.
    </div>
@else
    <div class="space-y-4">
        <dl class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="mt-1 text-sm font-semibold text-success-600 dark:text-success-400">Disetujui</dd>
            </div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Tanggal</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $consent->accepted_at?->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') }} WIB
                </dd>
            </div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Versi Konfigurasi</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                    v{{ $consent->configuration?->version ?? '-' }}
                </dd>
            </div>
        </dl>

        <details class="rounded-xl border border-gray-200 dark:border-white/10">
            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-950 dark:text-white">
                Lihat naskah yang disetujui
            </summary>
            <div class="border-t border-gray-200 p-4 dark:border-white/10">
                <h3 class="mb-3 text-base font-bold text-gray-950 dark:text-white">{{ $consent->title_snapshot }}</h3>
                <div class="text-sm leading-7 text-gray-700 dark:text-gray-200
                    [&_a]:text-primary-600 [&_a]:underline dark:[&_a]:text-primary-400
                    [&_h2]:mb-3 [&_h2]:mt-5 [&_h2]:font-bold
                    [&_h3]:mb-2 [&_h3]:mt-4 [&_h3]:font-bold
                    [&_li]:mb-2 [&_ol]:list-decimal [&_ol]:ps-6
                    [&_p]:mb-3 [&_ul]:list-disc [&_ul]:ps-6">
                    {!! $content !!}
                </div>
                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm font-medium text-gray-700 dark:bg-white/5 dark:text-gray-200">
                    {{ $consent->confirmation_snapshot }}
                </div>
                <p class="mt-3 break-all text-xs text-gray-500 dark:text-gray-400">
                    Hash naskah: {{ $consent->content_hash }}
                </p>
            </div>
        </details>
    </div>
@endif
