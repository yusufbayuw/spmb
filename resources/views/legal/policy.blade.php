<x-guest-layout>
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-950 dark:text-white">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Versi {{ $version }}
                @if($publishedAt)
                    · Berlaku sejak {{ $publishedAt->timezone(config('app.timezone'))->translatedFormat('d F Y H:i') }} WIB
                @endif
            </p>
        </div>

        <div class="text-sm leading-7 text-gray-700 dark:text-gray-200
            [&_a]:font-medium [&_a]:text-primary-600 [&_a]:underline dark:[&_a]:text-primary-400
            [&_blockquote]:border-s-4 [&_blockquote]:border-gray-300 [&_blockquote]:ps-4
            [&_h2]:mb-3 [&_h2]:mt-5 [&_h2]:text-lg [&_h2]:font-bold
            [&_h3]:mb-2 [&_h3]:mt-4 [&_h3]:font-bold
            [&_li]:mb-2 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:ps-6
            [&_p]:mb-3 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:ps-6">
            {!! $content !!}
        </div>

        <div>
            <a href="{{ url('/pendaftar/register') }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                Kembali ke pendaftaran akun
            </a>
        </div>
    </div>
</x-guest-layout>
