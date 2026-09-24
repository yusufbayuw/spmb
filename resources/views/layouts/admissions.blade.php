<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'Portal resmi penerimaan Yayasan Taruna Bakti untuk pendidikan anak usia dini, sekolah, dan perguruan tinggi.')">
    <meta name="theme-color" content="#1d4ed8">
    <meta name="robots" content="index,follow">
    <title>@yield('title', config('spmb.portal.name', 'Portal Penerimaan Taruna Bakti'))</title>

    @stack('meta')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Inter', sans-serif; }
        .admissions-grid {
            background-image:
                linear-gradient(rgba(15, 23, 42, .035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, .035) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        summary::-webkit-details-marker { display: none; }
    </style>
</head>
@php
    $portal = config('spmb.portal', []);
    $logoPath = filled($portal['logo_path'] ?? null) ? ltrim($portal['logo_path'], '/') : null;
    $hasOfficialLogo = $logoPath && file_exists(public_path($logoPath));
    $isStaff = auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'admin_unit', 'tu']);
    $dashboardUrl = $isStaff ? url('/admin') : url('/pendaftar');
@endphp
<body class="min-h-screen bg-white text-slate-950 antialiased">
    <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl">
        <nav class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8" aria-label="Navigasi utama">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-4">
                @if ($hasOfficialLogo)
                    <img src="{{ asset($logoPath) }}" alt="Logo {{ $portal['foundation_name'] ?? 'Yayasan Taruna Bakti' }}" class="h-11 w-auto shrink-0 object-contain">
                @endif
                <span class="min-w-0">
                    <span class="block truncate text-sm font-extrabold tracking-tight text-slate-950 sm:text-base">Portal Penerimaan</span>
                    <span class="block truncate text-xs font-medium text-slate-500">{{ $portal['foundation_name'] ?? 'Yayasan Taruna Bakti' }}</span>
                </span>
            </a>

            <div class="hidden items-center gap-7 lg:flex">
                <a href="{{ route('home') }}#pendaftaran" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-4">Pendaftaran</a>
                <a href="{{ route('home') }}#cara-mendaftar" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-4">Cara Mendaftar</a>
                <a href="{{ route('home') }}#bantuan" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-4">Bantuan</a>
            </div>

            <div class="hidden items-center gap-2 sm:flex">
                @auth
                    <a href="{{ $dashboardUrl }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                        {{ $isStaff ? 'Buka Panel Admin' : 'Buka Dashboard' }}
                    </a>
                @else
                    <a href="{{ url('/pendaftar/login') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">Masuk</a>
                    <a href="{{ url('/pendaftar/register') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">Daftar</a>
                @endauth
            </div>

            <details class="relative sm:hidden">
                <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-600" aria-label="Buka menu navigasi">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </summary>
                <div class="absolute right-0 mt-3 w-72 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl shadow-slate-900/10">
                    <a href="{{ route('home') }}#pendaftaran" class="block rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Pendaftaran</a>
                    <a href="{{ route('home') }}#cara-mendaftar" class="block rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cara Mendaftar</a>
                    <a href="{{ route('home') }}#bantuan" class="block rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Bantuan</a>
                    <div class="my-2 border-t border-slate-200"></div>
                    @auth
                        <a href="{{ $dashboardUrl }}" class="block rounded-xl bg-blue-700 px-4 py-3 text-center text-sm font-bold text-white">{{ $isStaff ? 'Buka Panel Admin' : 'Buka Dashboard' }}</a>
                    @else
                        <a href="{{ url('/pendaftar/login') }}" class="block rounded-xl px-4 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">Masuk</a>
                        <a href="{{ url('/pendaftar/register') }}" class="mt-1 block rounded-xl bg-blue-700 px-4 py-3 text-center text-sm font-bold text-white">Daftar</a>
                    @endauth
                </div>
            </details>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white py-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8">
            <div>
                <p class="font-extrabold text-slate-950">{{ $portal['foundation_name'] ?? 'Yayasan Taruna Bakti' }}</p>
                <p class="mt-1 text-sm text-slate-500">Portal resmi penerimaan · Daycare · KB · TK · SD · SMP · SMA · Universitas</p>
                @if (filled($portal['foundation_address'] ?? null))
                    <p class="mt-3 text-xs leading-5 text-slate-500">{{ $portal['foundation_address'] }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-slate-600">
                <a href="{{ route('home') }}#pendaftaran" class="hover:text-blue-700">Pendaftaran</a>
                <a href="{{ route('home') }}#cara-mendaftar" class="hover:text-blue-700">Cara Mendaftar</a>
                <a href="{{ route('home') }}#bantuan" class="hover:text-blue-700">Bantuan</a>
                @if (filled($portal['foundation_website'] ?? null))
                    <a href="{{ $portal['foundation_website'] }}" rel="noopener noreferrer" class="hover:text-blue-700">Website Yayasan</a>
                @endif
            </div>
        </div>
        <div class="mx-auto mt-6 max-w-7xl border-t border-slate-200 px-4 pt-6 text-xs text-slate-500 sm:px-6 lg:px-8">
            © {{ now()->year }} {{ $portal['foundation_name'] ?? 'Yayasan Taruna Bakti' }} · Sistem Penerimaan Terpadu
        </div>
    </footer>
</body>
</html>
