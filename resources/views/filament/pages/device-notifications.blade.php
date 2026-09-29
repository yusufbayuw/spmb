<x-filament-panels::page>
    <x-filament::section
        heading="Notifikasi perangkat"
        description="Push notification bekerja di luar tab browser setelah Anda mengizinkannya pada perangkat ini. Database notification di dalam aplikasi tetap aktif secara terpisah."
    >
        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                <p data-pwa-status>Memeriksa dukungan perangkat…</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <x-filament::button type="button" data-pwa-install-button hidden>
                    Install SPMB
                </x-filament::button>

                <x-filament::button type="button" color="success" data-pwa-enable-button hidden>
                    Aktifkan push notification
                </x-filament::button>

                <x-filament::button type="button" color="gray" data-pwa-disable-button hidden>
                    Nonaktifkan di perangkat ini
                </x-filament::button>
            </div>

            <div class="text-sm text-gray-500 dark:text-gray-400">
                <p>Setiap browser/perangkat memiliki subscription sendiri. Menonaktifkan push pada satu perangkat tidak menghapus database notification di aplikasi dan tidak mematikan push pada perangkat lain.</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
