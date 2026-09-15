@extends('layouts.admissions')

@php
    $title = $opening->studyProgram?->label() ?? $opening->unit?->name;
@endphp

@section('title')Lanjutkan atau Tambah Peserta | {{ $title }}@endsection
@section('description')Pilih pendaftaran yang sudah ada atau daftarkan calon peserta lain pada pembukaan yang sama.@endsection

@section('content')
    <section class="min-h-[70vh] bg-slate-50 py-12 sm:py-16">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <a href="{{ route('admissions.show', $opening) }}" class="text-sm font-bold text-blue-700 hover:text-blue-800">← Kembali ke detail pendaftaran</a>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">{{ $opening->unit?->name }}</p>
                <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">Pilih pendaftaran yang ingin dilanjutkan</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">
                    Akun ini sudah memiliki pendaftaran pada {{ $title }}, {{ $opening->wave }}. Anda dapat melanjutkan pendaftaran yang ada atau mendaftarkan calon peserta lain.
                </p>

                <div class="mt-8 grid gap-3">
                    @foreach ($existingRegistrations as $registration)
                        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-slate-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-extrabold text-slate-950">{{ $registration->full_name }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $registration->registration_number ?: 'Nomor registrasi belum diterbitkan' }}</p>
                                <p class="mt-2 text-xs font-semibold text-slate-500">Tahap: {{ $registration->stageLabel() }}</p>
                            </div>
                            <a href="{{ route('registration.show', $registration) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-100">
                                Lanjutkan Pendaftaran
                            </a>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 border-t border-slate-200 pt-6">
                    <h2 class="text-lg font-extrabold text-slate-950">Mendaftarkan calon peserta lain?</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Data alamat dan orang tua dari pendaftaran sebelumnya dapat digunakan sebagai isian awal. Identitas calon peserta tetap harus diisi sesuai dokumen resminya.</p>
                    <a href="{{ route('admissions.apply', ['registrationOpening' => $opening, 'new' => 1]) }}" class="mt-4 inline-flex min-h-12 items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-800">
                        + Daftarkan Peserta Lain
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
