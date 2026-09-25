<div
    class="grid gap-5"
    x-data="{ copied: false }"
>
    <div class="grid justify-items-center gap-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
        <img src="{{ $qrUrl }}" alt="QR Code {{ $title }}" class="aspect-square w-52 rounded-xl border border-gray-200 bg-white p-2">
        <p class="text-center text-sm font-semibold text-gray-950 dark:text-white">{{ $title }}</p>
        <p class="text-center text-xs leading-5 text-gray-500 dark:text-gray-400">QR mengarah ke halaman publik, bukan langsung ke login atau formulir.</p>
    </div>

    <div class="grid gap-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Link publik</label>
        <div class="flex gap-2">
            <input type="text" readonly value="{{ $publicUrl }}" class="min-w-0 flex-1 rounded-lg border-gray-300 bg-gray-50 text-sm text-gray-800 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-gray-100">
            <button
                type="button"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                x-on:click="navigator.clipboard.writeText(@js($publicUrl)); copied = true; setTimeout(() => copied = false, 1600)"
                x-text="copied ? 'Disalin' : 'Salin'"
            >Salin</button>
        </div>
    </div>

    <div class="grid gap-2 md:grid-cols-3">
        <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">Buka Halaman</a>
        <a href="{{ $qrUrl }}?download=1" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">Unduh QR</a>
        <a href="https://wa.me/?text={{ urlencode($shareText.' '.$publicUrl) }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">WhatsApp</a>
    </div>
</div>
