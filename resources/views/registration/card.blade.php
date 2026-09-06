@extends('registration.print-layout')
@section('title', 'Kartu Pendaftar')
@section('header-extra')
    @php
$photo = $registration->documents->firstWhere('type', 'photo');
@endphp
    @if($photo)<img class="photo" src="{{ route('files.applicant.documents.show', $photo) }}" alt="Foto peserta">@endif
@endsection
@section('content')
    <h2 class="number">{{ $registration->applicant_card_number }}</h2>
    <div class="grid">
        <div><span class="label">Nama peserta</span><span class="value">{{ $registration->full_name }}</span></div>
        <div><span class="label">Nomor pendaftaran</span><span class="value">{{ $registration->registration_number }}</span></div>
        <div><span class="label">NIK</span><span class="value">{{ $registration->nik }}</span></div>
        <div><span class="label">Tempat, tanggal lahir</span><span class="value">{{ $registration->birth_place }}, {{ $registration->birth_date->format('d M Y') }}</span></div>
        <div><span class="label">Jalur pendaftaran</span><span class="value">{{ $registration->pathway?->name ?? '—' }}</span></div>
        <div><span class="label">Program studi</span><span class="value">{{ $registration->opening?->studyProgram?->label() ?? '—' }}</span></div>
    </div>
    <p>Bawa kartu ini sebagai identitas peserta selama proses SPMB. Informasi jadwal tes tercantum pada kartu tes.</p>
@endsection
