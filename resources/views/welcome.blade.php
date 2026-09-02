<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Portal resmi SPMB Taruna Bakti untuk pendaftaran Daycare, KB, TK, SD, SMP, dan SMA. Daftar, pantau progres, pembayaran, dokumen, tes, dan pengumuman dalam satu portal.">
    <title>SPMB Taruna Bakti — Penerimaan Murid Baru</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Inter', sans-serif; }
        .hero-grid {
            background-image:
                linear-gradient(rgba(15, 23, 42, .035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, .035) 1px, transparent 1px);
            background-size: 32px 32px;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-950 antialiased">
    @php
        $portalUrl = auth()->check()
            ? (auth()->user()->hasAnyRole(['super_admin', 'tu']) ? url('/admin') : url('/pendaftar'))
            : null;
    @endphp

    <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
        <nav class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8" aria-label="Navigasi utama">
            <a href="{{ route('home') }}" class="group flex min-w-0 items-center gap-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-700 text-sm font-extrabold tracking-tight text-white shadow-sm shadow-blue-900/20 transition group-hover:bg-blue-800">TB</span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-extrabold tracking-tight text-slate-950 sm:text-base">SPMB Taruna Bakti</span>
                    <span class="block truncate text-xs font-medium text-slate-500">Penerimaan Murid Baru</span>
                </span>
            </a>

            <div class="hidden items-center gap-8 md:flex">
                <a href="#alur" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700">Alur Pendaftaran</a>
                <a href="#portal" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700">Fitur Portal</a>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                @auth
                    <a href="{{ $portalUrl }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 sm:px-5">
                        Buka Dashboard
                    </a>
                @else
                    <a href="{{ url('/pendaftar/login') }}" class="hidden min-h-10 items-center justify-center rounded-xl px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 sm:inline-flex">
                        Masuk
                    </a>
                    <a href="{{ url('/pendaftar/register') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 sm:px-5">
                        Daftar Sekarang
                    </a>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        <section class="hero-grid relative overflow-hidden border-b border-slate-200 bg-white">
            <div class="pointer-events-none absolute -right-40 -top-48 h-[34rem] w-[34rem] rounded-full bg-blue-100/70 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-64 -left-40 h-[30rem] w-[30rem] rounded-full bg-cyan-100/60 blur-3xl"></div>

            <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-[1.08fr_.92fr] lg:px-8 lg:py-28">
                <div class="max-w-3xl">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-bold uppercase tracking-[0.14em] text-blue-800">
                        <span class="h-2 w-2 rounded-full bg-blue-600"></span>
                        Portal resmi SPMB Taruna Bakti
                    </div>

                    <h1 class="text-balance text-4xl font-extrabold tracking-[-0.04em] text-slate-950 sm:text-5xl lg:text-6xl lg:leading-[1.08]">
                        Daftar sekali. Pantau seluruh proses sampai pengumuman.
                    </h1>

                    <p class="mt-6 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg">
                        Satu portal untuk pendaftaran Daycare, KB, TK, SD, SMP, dan SMA Taruna Bakti. Isi data calon siswa, ikuti proses validasi, pembayaran, dokumen, tes, dan pantau hasilnya dari satu dashboard.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        @auth
                            <a href="{{ $portalUrl }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-blue-700 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-700/15 transition hover:-translate-y-0.5 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                                Buka Dashboard
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        @else
                            <a href="{{ url('/pendaftar/register') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-blue-700 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-700/15 transition hover:-translate-y-0.5 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                                Mulai Pendaftaran
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                            <a href="{{ url('/pendaftar/login') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-bold text-slate-800 transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                                Saya sudah punya akun
                            </a>
                        @endauth
                    </div>

                    <div class="mt-7 flex flex-col gap-3 text-sm font-medium text-slate-600 sm:flex-row sm:flex-wrap sm:gap-x-6">
                        <span class="inline-flex items-center gap-2">
                            <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Satu akun untuk beberapa calon siswa
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h2m4 8V7a2 2 0 00-2-2h-2m-4 12H7a2 2 0 01-2-2v-2"/></svg>
                            Progres pendaftaran mudah dipantau
                        </span>
                    </div>
                </div>

                <div class="relative mx-auto w-full max-w-xl lg:mx-0 lg:ml-auto">
                    <div class="absolute -inset-4 rounded-[2rem] bg-blue-100/60 blur-2xl"></div>
                    <div class="relative overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-2xl shadow-slate-900/10">
                        <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-5 sm:px-7">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-700">Perjalanan pendaftaran</p>
                                    <h2 class="mt-1 text-lg font-extrabold tracking-tight text-slate-950">Semua tahap dalam satu dashboard</h2>
                                </div>
                                <span class="hidden rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 sm:inline-flex">Terpadu</span>
                            </div>
                        </div>

                        <div class="px-6 py-6 sm:px-7 sm:py-7">
                            <ol class="space-y-1" aria-label="Tahapan pendaftaran">
                                @foreach ([
                                    ['01', 'Isi data pendaftaran', 'Data calon siswa dan orang tua/wali'],
                                    ['02', 'Validasi & pembayaran', 'Validasi TU, VA, dan bukti pembayaran'],
                                    ['03', 'Dokumen & tes', 'Lengkapi berkas dan ikuti tahapan tes'],
                                    ['04', 'Seleksi & pengumuman', 'Pantau keputusan akhir dari dashboard'],
                                ] as [$number, $title, $description])
                                    <li class="group flex gap-4 rounded-2xl px-1 py-3.5">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-xs font-extrabold text-blue-700 ring-1 ring-inset ring-blue-100">
                                            {{ $number }}
                                        </div>
                                        <div class="min-w-0 pt-0.5">
                                            <h3 class="text-sm font-bold text-slate-900 sm:text-base">{{ $title }}</h3>
                                            <p class="mt-1 text-sm leading-6 text-slate-500">{{ $description }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>

                            <div class="mt-4 rounded-2xl border border-blue-100 bg-blue-50/80 p-4">
                                <div class="flex gap-3">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <p class="text-sm leading-6 text-blue-900"><strong class="font-bold">Tidak perlu membuat akun baru untuk setiap anak.</strong> Satu akun orang tua/wali dapat digunakan untuk beberapa pendaftaran.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-b border-slate-200 bg-white" aria-labelledby="unit-heading">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p id="unit-heading" class="text-sm font-bold text-slate-900">Unit pendidikan yang tersedia</p>
                        <p class="mt-1 text-sm text-slate-500">Pilih unit sekolah saat membuat pendaftaran calon siswa.</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap">
                        @foreach (['Daycare', 'KB', 'TK', 'SD', 'SMP', 'SMA'] as $unit)
                            <span class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-bold text-slate-700">{{ $unit }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="alur" class="bg-slate-50 py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Alur sederhana</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-[-0.03em] text-slate-950 sm:text-4xl">Mulai pendaftaran tanpa menebak langkah berikutnya</h2>
                    <p class="mt-4 text-base leading-7 text-slate-600">Portal mengarahkan proses dari pengisian data hingga pengumuman, sehingga orang tua dan calon siswa selalu mengetahui tahapan yang sedang berjalan.</p>
                </div>

                <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['1', 'Buat akun', 'Daftar sebagai orang tua/wali atau calon siswa dan gunakan satu akun untuk mengelola pendaftaran.'],
                        ['2', 'Daftarkan calon siswa', 'Pilih unit, isi identitas calon siswa, lalu lengkapi data ayah dan ibu.'],
                        ['3', 'Ikuti proses', 'Pantau validasi, VA, pembayaran, dokumen, dan tahapan tes dari dashboard.'],
                        ['4', 'Lihat hasil', 'Status seleksi dan pengumuman akhir tersedia pada pendaftaran masing-masing calon siswa.'],
                    ] as [$number, $title, $description])
                        <article class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <span class="absolute right-4 top-2 text-6xl font-extrabold tracking-tighter text-slate-100">{{ $number }}</span>
                            <div class="relative">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-700 text-sm font-extrabold text-white">{{ $number }}</div>
                                <h3 class="mt-5 text-lg font-extrabold tracking-tight text-slate-950">{{ $title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="portal" class="border-y border-slate-200 bg-white py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
                    <div class="lg:sticky lg:top-28">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Portal pendaftar</p>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-[-0.03em] text-slate-950 sm:text-4xl">Informasi penting tidak tercecer di banyak tempat</h2>
                        <p class="mt-5 max-w-xl text-base leading-7 text-slate-600">Setiap calon siswa memiliki progresnya sendiri, sementara orang tua tetap mengelola semuanya dari satu akun.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ([
                            ['heroicon-o-chart-bar', 'Status yang jelas', 'Lihat tahap aktif pendaftaran tanpa harus menanyakan progres secara manual.'],
                            ['heroicon-o-credit-card', 'VA & pembayaran', 'Nomor virtual account dan status verifikasi pembayaran tersimpan pada pendaftaran terkait.'],
                            ['heroicon-o-document-check', 'Dokumen terstruktur', 'Unggah dan pantau kelengkapan dokumen sesuai proses administrasi SPMB.'],
                            ['heroicon-o-academic-cap', 'Tes sampai pengumuman', 'Ikuti tahapan tes yang berlaku dan pantau hasil seleksi dari portal yang sama.'],
                        ] as [$icon, $title, $description])
                            <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-6 transition hover:border-blue-200 hover:bg-blue-50/40">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-blue-700 shadow-sm ring-1 ring-slate-200">
                                    @if ($icon === 'heroicon-o-chart-bar')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13.5h4V21H3v-7.5zM10 8h4v13h-4V8zm7-5h4v18h-4V3z"/></svg>
                                    @elseif ($icon === 'heroicon-o-credit-card')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h.01M11 15h2m-8 4h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    @elseif ($icon === 'heroicon-o-document-check')
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m2 8H7a2 2 0 01-2-2V6a2 2 0 012-2h5l5 5v7a2 2 0 01-2 2z"/></svg>
                                    @else
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422A12.083 12.083 0 0119 15.5c0 1.8-.4 3.5-1.1 5M12 14l-6.16-3.422A12.083 12.083 0 005 15.5c0 1.8.4 3.5 1.1 5"/></svg>
                                    @endif
                                </div>
                                <h3 class="mt-5 text-lg font-extrabold tracking-tight text-slate-950">{{ $title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-slate-950 py-16 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-[2rem] border border-white/10 bg-gradient-to-br from-blue-700 to-blue-900 px-6 py-10 shadow-2xl shadow-blue-950/20 sm:px-10 sm:py-12 lg:flex lg:items-center lg:justify-between lg:gap-12">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-100">Siap memulai?</p>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-[-0.03em] text-white sm:text-4xl">Mulai pendaftaran calon siswa dari satu akun.</h2>
                        <p class="mt-4 text-base leading-7 text-blue-100">Buat akun, tambahkan calon siswa, lalu ikuti setiap tahap SPMB melalui dashboard pendaftar.</p>
                    </div>

                    <div class="mt-8 flex shrink-0 flex-col gap-3 sm:flex-row lg:mt-0">
                        @auth
                            <a href="{{ $portalUrl }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-white px-6 py-3.5 text-sm font-extrabold text-blue-800 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-800">Buka Dashboard</a>
                        @else
                            <a href="{{ url('/pendaftar/register') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-white px-6 py-3.5 text-sm font-extrabold text-blue-800 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-800">Daftar Sekarang</a>
                            <a href="{{ url('/pendaftar/login') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white/25 px-6 py-3.5 text-sm font-extrabold text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-800">Masuk</a>
                        @endauth
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-700 text-xs font-extrabold text-white">TB</span>
                    <div>
                        <p class="text-sm font-bold text-slate-900">SPMB Taruna Bakti</p>
                        <p class="text-xs text-slate-500">Sistem Penerimaan Murid Baru</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-medium text-slate-500">
                    <span>&copy; {{ date('Y') }} Taruna Bakti</span>
                    <a href="{{ url('/admin/login') }}" class="transition hover:text-blue-700">Portal Petugas</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
