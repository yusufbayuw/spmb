@extends('layouts.admissions')

@php
    $portal = config('spmb.portal', []);
    $pageTitle = $headlineAcademicYear
        ? 'Penerimaan Taruna Bakti '.$headlineAcademicYear.' | '.$operationalProfile['portal_label']
        : 'Penerimaan Taruna Bakti | '.$operationalProfile['portal_label'];
@endphp

@section('title'){{ $pageTitle }}@endsection
@section('description'){{ $operationalProfile['page_description'] }}@endsection

@section('content')
    <section id="pendaftaran" class="scroll-mt-24 border-b border-slate-200 bg-white py-10 sm:py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">{{ $operationalProfile['public_eyebrow'] }}</p>
                <h1 class="mt-2 text-3xl font-extrabold tracking-[-0.03em] text-slate-950 sm:text-4xl">{{ $operationalProfile['homepage_heading'] }}</h1>
                <p class="mt-3 text-base leading-7 text-slate-600">{{ $operationalProfile['homepage_intro'] }}</p>
            </div>

            <div class="mt-6 flex flex-wrap gap-2" aria-label="Filter jenjang pendidikan">
                <a href="{{ route('home').'#pendaftaran' }}" class="rounded-full px-3.5 py-2 text-sm font-semibold {{ blank($selectedLevelCode) ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">Semua</a>
                @foreach ($educationLevels as $level)
                    <a href="{{ route('home', ['jenjang' => $level->code]).'#pendaftaran' }}" class="rounded-full px-3.5 py-2 text-sm font-semibold {{ $selectedLevelCode === $level->code ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">{{ $level->code }}</a>
                @endforeach
            </div>

            @if ($openOfferings->isNotEmpty())
                <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($openOfferings as $opening)
                        <x-admissions.opening-card :opening="$opening" />
                    @endforeach
                </div>
            @else
                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center">
                    <h2 class="text-xl font-extrabold text-slate-950">Belum ada pendaftaran yang sedang dibuka</h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm leading-7 text-slate-600">Saat ini belum tersedia pembukaan aktif untuk pilihan Anda. Periksa jadwal yang akan datang atau hubungi panitia penerimaan bila membutuhkan informasi lebih lanjut.</p>
                    <a href="#bantuan" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">Hubungi Panitia</a>
                </div>
            @endif
        </div>
    </section>

    @if (auth()->check() && auth()->user()->isUser() && $registrationPreviews->isNotEmpty())
        <section class="border-b border-slate-200 bg-slate-50 py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Pendaftaran Anda</p>
                        <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">Lanjutkan proses yang sedang berjalan</h2>
                    </div>
                    <a href="{{ url('/pendaftar/registrations') }}" class="text-sm font-bold text-blue-700 hover:text-blue-800">Lihat semua pendaftaran →</a>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-3">
                    @foreach ($registrationPreviews as $registration)
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-lg font-extrabold text-slate-950">{{ $registration->full_name }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-600">{{ $registration->opening?->studyProgram?->label() ?? $registration->unit?->name }}</p>
                            <div class="mt-4 rounded-xl bg-slate-50 p-3">
                                <p class="text-xs font-semibold text-slate-500">Tahap saat ini</p>
                                <p class="mt-1 text-sm font-bold text-slate-900">{{ $registration->stageLabel() }}</p>
                            </div>
                            <a href="{{ route('registration.show', $registration) }}" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Buka Pendaftaran</a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($upcomingOfferings->isNotEmpty())
        <section class="border-b border-slate-200 bg-slate-50 py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Akan segera dibuka</p>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Jadwal penerimaan berikutnya</h2>
                </div>
                <div class="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($upcomingOfferings as $opening)
                        <x-admissions.opening-card :opening="$opening" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="bg-white py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Pilih jenjang</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">{{ $operationalProfile['levels_heading'] }}</h2>
            </div>
            <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($educationLevels as $level)
                    <a href="{{ route('home', ['jenjang' => $level->code]).'#pendaftaran' }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 transition hover:border-blue-300 hover:bg-blue-50">
                        <p class="text-xs font-bold text-blue-700">{{ $level->code }}</p>
                        <p class="mt-2 font-extrabold text-slate-950">{{ $level->name }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $level->categoryLabel() }}</p>
                        <p class="mt-3 text-sm font-bold text-blue-700">Lihat pendaftaran →</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section id="cara-mendaftar" class="scroll-mt-24 border-y border-slate-200 bg-slate-50 py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Cara mendaftar</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">Empat langkah untuk memulai</h2>
            </div>
            <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['01', 'Pilih pendaftaran', 'Temukan jenjang atau program studi yang sedang tersedia.'],
                    ['02', 'Buat & verifikasi akun', 'Gunakan email aktif yang dapat Anda akses.'],
                    ['03', 'Lengkapi data', 'Isi identitas dan dokumen sesuai kebutuhan unit serta jalur.'],
                    ['04', 'Pantau proses', 'Lihat status, pembayaran, seleksi, dan hasil melalui dashboard.'],
                ] as [$number, $stepTitle, $stepText])
                    <article class="rounded-2xl border border-slate-200 bg-white p-6">
                        <span class="text-xs font-extrabold tracking-[0.12em] text-blue-700">{{ $number }}</span>
                        <h3 class="mt-4 text-lg font-extrabold text-slate-950">{{ $stepTitle }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $stepText }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white py-16 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:items-start lg:px-8">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Sebelum mendaftar</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">Siapkan informasi utama</h2>
                <p class="mt-4 text-sm leading-7 text-slate-600">Persyaratan rinci dapat berbeda per unit dan jalur. Portal akan menampilkan kebutuhan yang sesuai setelah Anda memilih pembukaan.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ([
                    ['Email aktif', 'Untuk aktivasi akun dan notifikasi proses penerimaan.'],
                    ['Identitas resmi', 'Gunakan data calon peserta sesuai dokumen resmi.'],
                    ['Nomor telepon aktif', 'Untuk komunikasi selama proses penerimaan.'],
                    ['Dokumen pendukung', 'Disesuaikan dengan jenjang dan jalur pendaftaran yang dipilih.'],
                ] as [$itemTitle, $itemText])
                    <div class="rounded-xl border border-slate-200 p-5">
                        <p class="font-extrabold text-slate-950">{{ $itemTitle }}</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $itemText }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-slate-950 py-16 text-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-300">{{ $portal['foundation_name'] ?? 'Yayasan Taruna Bakti' }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight">{{ $operationalProfile['scope_heading'] }}</h2>
                <p class="mt-4 text-sm leading-7 text-slate-300">{{ $operationalProfile['scope_description'] }}</p>
                @if (filled($portal['foundation_website'] ?? null))
                    <a href="{{ $portal['foundation_website'] }}" rel="noopener noreferrer" class="mt-5 inline-flex min-h-11 items-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-bold text-white hover:bg-white/10">Kunjungi Website Yayasan →</a>
                @endif
            </div>
        </div>
    </section>

    <section id="bantuan" class="scroll-mt-24 bg-slate-50 py-16 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:px-8">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Bantuan</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">Ada kendala saat mendaftar?</h2>
                <p class="mt-4 text-sm leading-7 text-slate-600">
                    {{ \App\Support\SpmbOperationalMode::isHigherEducation()
                        ? 'Hubungi kontak resmi perguruan tinggi yang menangani program studi tujuan Anda.'
                        : 'Hubungi kanal resmi penerimaan. Pada detail pembukaan, kontak unit akan ditampilkan bila tersedia.' }}
                </p>
            </div>
            @if (\App\Support\SpmbOperationalMode::isHigherEducation())
                <div class="grid gap-4">
                    @forelse ($helpdeskUnits as $helpdeskUnit)
                        <x-admissions.helpdesk :unit="$helpdeskUnit" />
                    @empty
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
                            Kontak penerimaan perguruan tinggi belum dikonfigurasi. Silakan periksa kembali halaman ini setelah admin unit melengkapi informasi helpdesk.
                        </div>
                    @endforelse
                </div>
            @else
                <x-admissions.helpdesk />
            @endif
        </div>
    </section>
@endsection