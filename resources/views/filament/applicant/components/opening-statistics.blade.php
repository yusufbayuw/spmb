@if ($opening && ($opening->show_total_applicants || $opening->show_verified_applicants))
    <div class="grid gap-3 {{ $opening->show_total_applicants && $opening->show_verified_applicants ? 'md:grid-cols-2' : '' }}">
        @if ($opening->show_total_applicants)
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <x-filament::icon icon="heroicon-o-users" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format((int) $opening->applicant_total_count, 0, ',', '.') }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total Pendaftar</div>
                </div>
            </div>
        @endif

        @if ($opening->show_verified_applicants)
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400">
                    <x-filament::icon icon="heroicon-o-check-badge" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <div class="text-xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format((int) $opening->applicant_verified_count, 0, ',', '.') }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Data Terverifikasi</div>
                </div>
            </div>
        @endif
    </div>
@endif
