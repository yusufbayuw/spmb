@extends('layouts.admissions')

@php
    $status = $opening->operationalStatus();
    $isOpen = $status === 'open';
    $isScheduled = $status === 'scheduled';
    $title = $opening->studyProgram?->label() ?? $opening->unit?->name;
    $periodLabel = $opening->unit?->isHigherEducation() ? 'Tahun Akademik' : 'Tahun Ajaran';
    $fee = (float) $opening->registration_fee === 0.0 ? 'Gratis' : $opening->formattedFee();
    $statusLabel = match ($status) {
        'open' => 'Dibuka',
        'scheduled' => 'Akan Dibuka',
        'closed' => 'Ditutup',
        default => $opening->statusLabel(),
    };
    $statusClasses = match ($status) {
        'open' => 'bg-emerald-100 text-emerald-800',
        'scheduled' => 'bg-blue-100 text-blue-800',
        default => 'bg-slate-200 text-slate-700',
    };
    $metaTitle = $title.' · '.$opening->academic_year.' · '.$opening->wave.' | Penerimaan Taruna Bakti';
    $metaDescription = 'Informasi '.$title.' '.$periodLabel.' '.$opening->academic_year.', '.$opening->wave.', biaya, jadwal, jalur pendaftaran, dan bantuan penerimaan.';
@endphp

@section('title'){{ $metaTitle }}@endsection
@section('description'){{ $metaDescription }}@endsection

@push('meta')
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('admissions.show', $opening) }}">
@endpush

@section('content')
    <section class="border-b border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
            <nav class="text-sm font-semibold text-slate-500" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-blue-700">Portal Penerimaan</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-slate-800">{{ $title }}</span>
            </nav>

            @if (session('admission_notice'))
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900" role="status">
                    {{ session('admission_notice') }}
                </div>
            @endif

            <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
                <div>
                    @if ($opening->studyProgram)
                        <p class="text-sm font-bold text-blue-700">{{ $opening->unit?->name }}</p>
                    @endif
                    <div class="mt-2 flex flex-wrap items-start gap-3">
                        <h1 class="max-w-4xl text-3xl font-extrabold tracking-[-0.03em] text-slate-950 sm:text-4xl lg:text-5xl">{{ $title }}</h1>
                        <span class="mt-1 rounded-full px-3 py-1.5 text-xs font-bold {{ $statusClasses }}">{{ $statusLabel }}</span>
                    </div>
                    <p class="mt-4 text-lg font-semibold text-slate-600">{{ $periodLabel }} {{ $opening->academic_year }} · {{ $opening->wave }}</p>

                    @if ($opening->description)
                        <p class="mt-7 max-w-3xl text-base leading-8 text-slate-600">{{ $opening->description }}</p>
                    @endif

                    @if ($existingRegistrations->isNotEmpty())
                        <div class="mt-8 rounded-2xl border border-blue-200 bg-blue-50 p-5">
                            <p class="text-sm font-extrabold text-blue-950">Anda sudah memiliki pendaftaran pada pembukaan ini.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($existingRegistrations as $registration)
                                    <a href="{{ route('registration.show', $registration) }}" class="rounded-xl border border-blue-200 bg-white px-3 py-2 text-sm font-bold text-blue-800 hover:border-blue-300">
                                        {{ $registration->full_name }} · Lanjutkan
                                    </a>
                                @endforeach
                            </div>
                            @if ($isOpen)
                                <p class="mt-3 text-sm leading-6 text-blue-900">Anda tetap dapat mendaftarkan calon peserta lain pada pembukaan yang sama.</p>
                            @endif
                        </div>
                    @endif
                </div>

                <aside class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:sticky lg:top-28">
                    <dl class="grid gap-5">
                        <div>
                            <dt class="text-xs font-semibold text-slate-500">{{ $isScheduled ? 'Pendaftaran dibuka' : ($isOpen ? 'Batas pendaftaran' : 'Pendaftaran ditutup') }}</dt>
                            <dd class="mt-1 text-sm font-extrabold text-slate-950">
                                @php($importantDate = $isScheduled ? $opening->opened_at : $opening->closed_at)
                                {{ $importantDate ? $importantDate->translatedFormat('d F Y · H:i').' WIB' : 'Mengikuti informasi unit' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-slate-500">Biaya pendaftaran</dt>
                            <dd class="mt-1 text-lg font-extrabold text-slate-950">{{ $fee }}</dd>
                        </div>
                    </dl>

                    @if ($isOpen)
                        <a href="{{ route('admissions.apply', $opening) }}" class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                            Daftar Sekarang
                        </a>
                    @elseif ($isScheduled)
                        <p class="mt-6 rounded-xl bg-blue-50 px-4 py-3 text-sm font-semibold leading-6 text-blue-900">Pendaftaran belum dapat dimulai. Silakan kembali setelah waktu pembukaan.</p>
                    @else
                        <a href="{{ route('home') }}#pendaftaran" class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                            Lihat Pendaftaran Lain
                        </a>
                    @endif
                </aside>
            </div>
        </div>
    </section>

    <section class="bg-white py-16 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-8">
            <div class="space-y-12">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Jalur pendaftaran</p>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">Pilihan jalur yang tersedia</h2>
                    @if ($pathways->isNotEmpty())
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            @foreach ($pathways as $pathway)
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="font-bold text-slate-900">{{ $pathway->name }}</p>
                                    @if ($pathway->description)
                                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $pathway->description }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-sm leading-7 text-slate-600">Jalur pendaftaran akan ditampilkan pada formulir sesuai konfigurasi unit.</p>
                    @endif
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Sebelum mendaftar</p>
                    <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">Siapkan informasi utama</h2>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            ['Email aktif', 'Digunakan untuk aktivasi akun dan notifikasi proses penerimaan.'],
                            ['Identitas resmi', 'Gunakan data calon peserta sesuai dokumen resmi.'],
                            ['Nomor telepon aktif', 'Untuk komunikasi selama proses penerimaan.'],
                            ['Dokumen pendukung', 'Dokumen yang diperlukan akan mengikuti unit dan jalur yang dipilih.'],
                        ] as [$itemTitle, $itemText])
                            <div class="rounded-xl border border-slate-200 p-4">
                                <p class="font-bold text-slate-900">{{ $itemTitle }}</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $itemText }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <x-admissions.helpdesk :unit="$opening->unit" />
        </div>
    </section>
@endsection
