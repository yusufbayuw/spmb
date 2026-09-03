<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Portal penerimaan Taruna Bakti untuk SPMB Daycare, KB, TK, SD, SMP, SMA serta PMB Taruna Bakti University dengan pilihan program studi Diploma dan Sarjana.">
    <title>Penerimaan Taruna Bakti — SPMB Sekolah & PMB Universitas</title>

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
        $studyProgramCount = $universities->sum(fn ($unit) => $unit->studyPrograms->count());
    @endphp

    <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
        <nav class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8" aria-label="Navigasi utama">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-700 text-sm font-extrabold tracking-tight text-white shadow-sm">TB</span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-extrabold tracking-tight text-slate-950 sm:text-base">Penerimaan Taruna Bakti</span>
                    <span class="block truncate text-xs font-medium text-slate-500">SPMB Sekolah · PMB Universitas</span>
                </span>
            </a>

            <div class="hidden items-center gap-7 lg:flex">
                <a href="#pilihan" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700">Pilihan Pendidikan</a>
                <a href="#alur" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700">Alur Pendaftaran</a>
                <a href="#program-studi" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700">Program Studi</a>
                <a href="#informasi" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700">Informasi</a>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                @auth
                    <a href="{{ $portalUrl }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-800 sm:px-5">Buka Dashboard</a>
                @else
                    <a href="{{ url('/pendaftar/login') }}" class="hidden min-h-10 items-center justify-center rounded-xl px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100 sm:inline-flex">Masuk</a>
                    <a href="{{ url('/pendaftar/register') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-800 sm:px-5">Buat Akun</a>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        <section class="hero-grid relative overflow-hidden border-b border-slate-200 bg-white">
            <div class="pointer-events-none absolute -right-40 -top-48 h-[34rem] w-[34rem] rounded-full bg-blue-100/70 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-64 -left-40 h-[30rem] w-[30rem] rounded-full bg-cyan-100/60 blur-3xl"></div>

            <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-[1.1fr_.9fr] lg:px-8 lg:py-28">
                <div class="max-w-3xl">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-bold uppercase tracking-[0.14em] text-blue-800">
                        <span class="h-2 w-2 rounded-full bg-blue-600"></span>
                        Daycare–SMA & Taruna Bakti University
                    </div>
                    <h1 class="text-balance text-4xl font-extrabold tracking-[-0.04em] text-slate-950 sm:text-5xl lg:text-6xl lg:leading-[1.08]">
                        Satu portal untuk perjalanan pendidikan di Taruna Bakti.
                    </h1>
                    <p class="mt-6 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg">
                        Daftar ke pendidikan anak usia dini, sekolah dasar dan menengah, atau pilih program Diploma dan Sarjana di Taruna Bakti University. Akun, verifikasi email, data pendaftaran, pembayaran, berkas, seleksi, notifikasi, dan pengumuman dikelola dari satu portal.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        @auth
                            <a href="{{ $portalUrl }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-blue-700 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-700/15 transition hover:-translate-y-0.5 hover:bg-blue-800">Buka Dashboard</a>
                        @else
                            <a href="{{ url('/pendaftar/register') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-blue-700 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-700/15 transition hover:-translate-y-0.5 hover:bg-blue-800">Mulai Pendaftaran</a>
                            <a href="{{ url('/pendaftar/login') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-bold text-slate-800 transition hover:bg-slate-50">Saya sudah punya akun</a>
                        @endauth
                    </div>

                    <div class="mt-8 grid max-w-xl grid-cols-3 gap-3">
                        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                            <div class="text-2xl font-extrabold text-slate-950">{{ $schoolUnits->count() }}</div>
                            <div class="mt-1 text-xs font-semibold text-slate-500">Unit sekolah/PAUD</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                            <div class="text-2xl font-extrabold text-slate-950">{{ $studyProgramCount }}</div>
                            <div class="mt-1 text-xs font-semibold text-slate-500">Program studi</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                            <div class="text-2xl font-extrabold text-slate-950">{{ $openOfferings->count() }}</div>
                            <div class="mt-1 text-xs font-semibold text-slate-500">Pembukaan aktif</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-2xl shadow-slate-900/10 sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-700">Sebelum mendaftar</p>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">Siapkan email aktif dan identitas resmi</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Tautan aktivasi/verifikasi dikirim ke email pendaftar. Setelah akun aktif, pilih pembukaan yang sesuai lalu lengkapi data dan dokumen.</p>

                    <ol class="mt-7 space-y-4">
                        @foreach ([
                            ['01', 'Buat akun', 'Gunakan alamat email aktif yang dapat Anda akses.'],
                            ['02', 'Verifikasi email', 'Klik tautan verifikasi sebelum membuat pendaftaran.'],
                            ['03', 'Pilih tujuan', 'Pilih unit sekolah atau program studi dan gelombang.'],
                            ['04', 'Pantau proses', 'Ikuti verifikasi, pembayaran, berkas, tes, dan hasil.'],
                        ] as [$number, $title, $text])
                            <li class="flex gap-4">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-xs font-extrabold text-blue-700">{{ $number }}</span>
                                <div><div class="font-bold text-slate-900">{{ $title }}</div><p class="mt-1 text-sm leading-6 text-slate-500">{{ $text }}</p></div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>

        <section id="pilihan" class="border-b border-slate-200 bg-slate-50 py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Dua jalur dalam satu sistem</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-[-0.03em] text-slate-950 sm:text-4xl">Dari pendidikan anak hingga perguruan tinggi</h2>
                    <p class="mt-4 text-base leading-7 text-slate-600">Struktur pendaftaran menyesuaikan tujuan pendidikan. Sekolah menggunakan unit pendidikan; perguruan tinggi menggunakan institusi sekaligus program studi.</p>
                </div>

                <div class="mt-12 grid gap-6 lg:grid-cols-2">
                    <article class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm sm:p-8">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">SPMB Yayasan Taruna Bakti</span>
                                <h3 class="mt-4 text-2xl font-extrabold text-slate-950">Pendidikan dasar & menengah</h3>
                            </div>
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-700 text-lg font-extrabold text-white">S</span>
                        </div>
                        <p class="mt-4 text-sm leading-7 text-slate-600">Pendaftaran murid dilaksanakan secara daring. Setelah akun dan email terverifikasi, orang tua/wali atau calon siswa mengisi data, kemudian petugas melakukan verifikasi sebelum proses pembayaran, kartu peserta, berkas, tes, dan seleksi dilanjutkan.</p>
                        <div class="mt-6 flex flex-wrap gap-2">
                            @foreach ($schoolUnits as $unit)
                                <span class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm font-bold text-slate-700">{{ $unit->name }}</span>
                            @endforeach
                        </div>
                    </article>

                    <article class="rounded-3xl border border-blue-200 bg-blue-950 p-7 text-white shadow-sm sm:p-8">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-blue-100">PMB Taruna Bakti University</span>
                                <h3 class="mt-4 text-2xl font-extrabold">Diploma & Sarjana</h3>
                            </div>
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-lg font-extrabold text-blue-800">U</span>
                        </div>
                        <p class="mt-4 text-sm leading-7 text-blue-100">Calon mahasiswa memilih program studi sejak awal pembukaan. Sistem menyimpan prodi, tahun akademik, gelombang, jalur dan biaya formulir sebagai satu offering sehingga banyak program studi dapat berjalan pada periode yang sama.</p>
                        <div class="mt-6 flex flex-wrap gap-2">
                            @foreach ($universities->flatMap->studyPrograms as $program)
                                <span class="rounded-xl border border-white/15 bg-white/10 px-3.5 py-2 text-sm font-bold text-white">{{ $program->label() }}</span>
                            @endforeach
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="alur" class="bg-white py-20 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Alur penerimaan</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-[-0.03em] text-slate-950 sm:text-4xl">Pendaftaran daring, verifikasi terkontrol, progres transparan</h2>
                    <p class="mt-4 text-base leading-7 text-slate-600">Narasi alur mengikuti pola penerimaan resmi Taruna Bakti: akun dan email aktif, pengisian formulir, verifikasi petugas, pembayaran, kelengkapan administrasi, tahapan seleksi, lalu pengumuman.</p>
                </div>

                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['01', 'Buat akun', 'Daftarkan email aktif untuk memperoleh akses portal penerimaan.'],
                        ['02', 'Verifikasi email', 'Aktifkan akun melalui tautan yang dikirim ke email.'],
                        ['03', 'Pilih pembukaan', 'Pilih unit sekolah atau program studi, gelombang, dan jalur.'],
                        ['04', 'Lengkapi formulir', 'Isi identitas resmi, riwayat pendidikan, dan data pendukung yang diminta.'],
                        ['05', 'Verifikasi & pembayaran', 'Petugas memeriksa data; sistem menerbitkan VA dan memproses bukti pembayaran.'],
                        ['06', 'Kartu, berkas & seleksi', 'Unduh kartu, lengkapi berkas, ikuti tes bila ada, lalu pantau pengumuman.'],
                    ] as [$number, $title, $text])
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                            <span class="text-xs font-extrabold tracking-[0.15em] text-blue-700">{{ $number }}</span>
                            <h3 class="mt-4 text-lg font-extrabold text-slate-950">{{ $title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $text }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        @if ($universities->isNotEmpty())
            <section id="program-studi" class="border-y border-slate-200 bg-slate-50 py-20 sm:py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-3xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Taruna Bakti University</p>
                            <h2 class="mt-3 text-3xl font-extrabold tracking-[-0.03em] text-slate-950 sm:text-4xl">Pilih program studi tujuan</h2>
                            <p class="mt-4 text-base leading-7 text-slate-600">Program studi dikelola sebagai master data dan dapat memiliki pembukaan, gelombang, jalur, biaya serta ketentuan usia masing-masing.</p>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">{{ $studyProgramCount }} program studi aktif</span>
                    </div>

                    <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($universities->flatMap->studyPrograms as $program)
                            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-extrabold text-blue-700">{{ $program->degree_level }}</span>
                                    @if ($program->max_age)
                                        <span class="text-xs font-semibold text-amber-700">Usia maks. {{ $program->max_age }} tahun</span>
                                    @endif
                                </div>
                                <h3 class="mt-4 text-xl font-extrabold text-slate-950">{{ $program->label() }}</h3>
                                @if ($program->faculty)
                                    <p class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-500">{{ $program->faculty }}</p>
                                @endif
                                <p class="mt-4 text-sm leading-6 text-slate-600">{{ $program->description }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($openOfferings->isNotEmpty())
            <section class="bg-white py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Sedang tersedia</p>
                            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">Pembukaan pendaftaran aktif</h2>
                        </div>
                        @auth
                            <a href="{{ url('/pendaftar/pendaftaran') }}" class="text-sm font-bold text-blue-700 hover:text-blue-800">Lihat semua di portal →</a>
                        @else
                            <a href="{{ url('/pendaftar/register') }}" class="text-sm font-bold text-blue-700 hover:text-blue-800">Buat akun untuk mendaftar →</a>
                        @endauth
                    </div>

                    <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($openOfferings->take(9) as $opening)
                            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700">{{ $opening->unit?->name }}</p>
                                        <h3 class="mt-2 text-lg font-extrabold text-slate-950">{{ $opening->studyProgram?->label() ?? $opening->unit?->name }}</h3>
                                    </div>
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">Dibuka</span>
                                </div>
                                <div class="mt-4 space-y-1 text-sm text-slate-600">
                                    <p>TA {{ $opening->academic_year }} · {{ $opening->wave }}</p>
                                    <p>Jalur {{ $opening->pathway }}</p>
                                    <p class="font-bold text-slate-900">{{ $opening->formattedFee() }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section id="informasi" class="border-t border-slate-200 bg-slate-950 py-20 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-300">Portal terpadu</p>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight">Data penerimaan tetap terpisah sesuai institusi, tetapi pengalaman pendaftar tetap satu.</h2>
                        <p class="mt-4 text-sm leading-7 text-slate-300">Dokumen disimpan privat, email wajib diverifikasi, tindakan sensitif diaudit, dan perubahan tahap menghasilkan notifikasi yang dapat ditelusuri.</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-300">SPMB Yayasan Taruna Bakti</p>
                        <h3 class="mt-3 text-lg font-extrabold">Daycare, KB, TK, SD, SMP, SMA</h3>
                        <p class="mt-3 text-sm leading-6 text-slate-300">Verifikasi pendaftaran dilayani petugas unit. Informasi resmi SPMB menggunakan kanal Yayasan Taruna Bakti.</p>
                        <div class="mt-5 text-sm leading-6 text-slate-300">
                            <p>Jl. L.L.R.E. Martadinata No. 52, Bandung</p>
                            <p>Helpdesk hari kerja 08.00–14.00 WIB</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-cyan-300">PMB Taruna Bakti University</p>
                        <h3 class="mt-3 text-lg font-extrabold">Diploma & Sarjana</h3>
                        <p class="mt-3 text-sm leading-6 text-slate-300">Pendaftaran mahasiswa diverifikasi oleh petugas kampus. Batas usia disimpan per program studi; konfigurasi awal S1 mengikuti ketentuan maksimal 26 tahun.</p>
                        <div class="mt-5 text-sm leading-6 text-slate-300">
                            <p>Jl. L.L.R.E. Martadinata No. 93–95, Bandung</p>
                            <p>Hotline PMB: 0888-2000-011</p>
                        </div>
                    </div>
                </div>

                <div class="mt-12 flex flex-col items-start justify-between gap-5 border-t border-white/10 pt-8 sm:flex-row sm:items-center">
                    <div>
                        <p class="font-extrabold">Siap memulai pendaftaran?</p>
                        <p class="mt-1 text-sm text-slate-400">Buat satu akun, verifikasi email, lalu pilih pembukaan yang sesuai.</p>
                    </div>
                    @auth
                        <a href="{{ $portalUrl }}" class="inline-flex min-h-11 items-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-slate-950">Buka Dashboard</a>
                    @else
                        <a href="{{ url('/pendaftar/register') }}" class="inline-flex min-h-11 items-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-500">Buat Akun Pendaftar</a>
                    @endauth
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white py-6">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>© {{ now()->year }} Yayasan Taruna Bakti · Sistem Penerimaan Terpadu</p>
            <p>SPMB Sekolah · PMB Taruna Bakti University</p>
        </div>
    </footer>
</body>
</html>
