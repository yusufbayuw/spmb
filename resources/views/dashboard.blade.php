@extends('layouts.app')

@section('title', 'Dashboard Pendaftar - SPMB Taruna Bakti')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-gray-900">Pendaftaran Saya</h1><p class="text-gray-600">Satu akun dapat mengelola beberapa calon siswa.</p></div>
        <a href="{{ route('registration.create') }}" class="btn-primary">+ Daftarkan Calon Siswa</a>
    </div>

    @if(session('success'))<div class="bg-green-50 border border-green-200 text-green-800 rounded-xl p-4">{{ session('success') }}</div>@endif

    @forelse($registrations as $registration)
        <div class="bg-white rounded-xl card-shadow p-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <div class="text-sm text-blue-600 font-semibold">{{ $registration->registration_number }}</div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $registration->full_name }}</h2>
                    <p class="text-gray-600">{{ $registration->unit->name }} · {{ $registration->registrant_type === 'parent' ? 'Didaftarkan orang tua/wali' : 'Daftar langsung' }}</p>
                </div>
                <span class="inline-flex rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-sm font-semibold">{{ $registration->stageLabel() }}</span>
            </div>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('registration.show', $registration) }}" class="btn-primary">Lihat Progres</a>
                @if($registration->current_stage === 'payment' && $registration->latestPayment)<a href="{{ route('registration.payment', $registration) }}" class="px-4 py-2 rounded-lg bg-amber-100 text-amber-800 font-semibold">Upload Pembayaran</a>@endif
                @if(in_array($registration->current_stage, ['documents','document_verification']))<a href="{{ route('registration.documents', $registration) }}" class="px-4 py-2 rounded-lg bg-indigo-100 text-indigo-800 font-semibold">Lengkapi Berkas</a>@endif
                @if($registration->applicant_card_number)<a href="{{ route('registration.card', $registration) }}" target="_blank" class="px-4 py-2 rounded-lg bg-emerald-100 text-emerald-800 font-semibold">Kartu Pendaftar</a>@endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl card-shadow p-10 text-center"><h2 class="font-bold text-lg">Belum ada pendaftaran</h2><p class="text-gray-600 mt-2">Mulai dengan mendaftarkan diri sendiri atau anak Anda.</p></div>
    @endforelse
</div>
@endsection
