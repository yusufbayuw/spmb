@extends('layouts.admissions')

@php
    $pageTitle = 'Penerimaan '.$unit->name.' | Taruna Bakti';
    $pageDescription = 'Halaman resmi penerimaan '.$unit->name.'. Lihat pendaftaran yang sedang dibuka, jadwal berikutnya, biaya, dan bantuan pendaftaran.';
    $shareUrl = route('admissions.unit', ['unit' => $unit->code]);
    $qrUrl = route('admissions.unit.qr', ['unit' => $unit->code]);
@endphp

@section('title'){{ $pageTitle }}@endsection
@section('description'){{ $pageDescription }}@endsection

@push('meta')
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $shareUrl }}">
@endpush

@section('content')
    <section class="border-b border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
            <nav class="text-sm font-semibold text-slate-500" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-blue-700">Portal Penerimaan</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-slate-800">{{ $unit->name }}</span>
            </nav>

            <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                <div class="max-w-4xl">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Halaman resmi unit</p>
                    <h1 class="mt-3 text-4xl font-extrabold tracking-[-0.04em] text-slate-950 sm:text-5xl">{{ $unit->public_headline ?: 'Penerimaan '.$unit->name }}</h1>
                    @if ($unit->description)
                        <p class="mt-5 max-w-3xl text-base leading-8 text-slate-600 sm:text-lg">{{ $unit->description }}</p>
                    @else
                        <p class="mt-5 max-w-3xl text-base leading-8 text-slate-600 sm:text-lg">Lihat pembukaan pendaftaran terbaru, jadwal, biaya, dan mulai pendaftaran melalui portal resmi Yayasan Taruna Bakti.</p>
                    @endif
                </div>

                <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-extrabold text-slate-950">Bagikan halaman unit</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Link ini bersifat permanen dan akan selalu menampilkan pembukaan terbaru unit.</p>
                    <img src="{{ $qrUrl }}" alt="QR Code penerimaan {{ $unit->name }}" class="mx-auto mt-4 aspect-square w-40 rounded-xl border border-slate-200 bg-white p-2">
                    <div class="mt-4 grid gap-2">
                        <button type="button" onclick="navigator.clipboard?.writeText(@js($shareUrl)); this.textContent='Link disalin'; setTimeout(() => this.textContent='Salin Link', 1600)" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800">Salin Link</button>
                        <a href="{{ $qrUrl }}?download=1" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Unduh QR</a>
                        <a href="https://wa.me/?text={{ urlencode('Penerimaan '.$unit->name.' '.$shareUrl) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Bagikan via WhatsApp</a>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    @if ($unit->public_body)
        <section class="border-b border-slate-200 bg-white py-14 sm:py-16">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="cms-content">{!! $unit->public_body !!}</div>
            </div>
        </section>
    @endif

    <section class="bg-white py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Pendaftaran saat ini</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-[-0.03em] text-slate-950">Pembukaan yang sedang tersedia</h2>
            </div>

            @if ($openings->isNotEmpty())
                <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($openings as $opening)
                        <x-admissions.opening-card :opening="$opening" />
                    @endforeach
                </div>
            @elseif ($upcomingOpenings->isNotEmpty())
                <div class="mt-8 rounded-2xl border border-blue-200 bg-blue-50 p-6 sm:p-8">
                    <h3 class="text-xl font-extrabold text-blue-950">Pendaftaran berikutnya akan segera dibuka</h3>
                    <p class="mt-2 text-sm leading-6 text-blue-900">Simpan halaman ini atau bagikan QR unit. Informasi akan otomatis mengikuti pembukaan terbaru.</p>
                </div>
            @else
                <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center">
                    <h3 class="text-xl font-extrabold text-slate-950">Belum ada pendaftaran yang sedang dibuka</h3>
                    <p class="mx-auto mt-2 max-w-2xl text-sm leading-7 text-slate-600">Silakan lihat informasi pembukaan terakhir atau hubungi panitia {{ $unit->name }} untuk jadwal penerimaan berikutnya.</p>
                </div>
            @endif

            @if ($upcomingOpenings->isNotEmpty())
                <div class="mt-14">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Akan dibuka</p>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">Jadwal berikutnya</h2>
                    <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($upcomingOpenings as $opening)
                            <x-admissions.opening-card :opening="$opening" />
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($recentClosedOpenings->isNotEmpty())
                <div class="mt-14">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Pendaftaran terakhir</p>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">Pembukaan yang telah ditutup</h2>
                    <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($recentClosedOpenings as $opening)
                            <x-admissions.opening-card :opening="$opening" />
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    <x-admissions.faqs :faqs="$faqs" />

    <section class="border-t border-slate-200 bg-slate-50 py-16">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-8">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Portal resmi</p>
                <h2 class="mt-2 text-2xl font-extrabold text-slate-950">Satu link yang tetap relevan setiap periode</h2>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">QR dan link halaman unit dapat digunakan pada brosur, poster, presentasi, media sosial, dan WhatsApp tanpa perlu diganti ketika gelombang atau tahun ajaran berubah.</p>
            </div>
            <x-admissions.helpdesk :unit="$unit" />
        </div>
    </section>
@endsection
