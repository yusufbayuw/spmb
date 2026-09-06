@extends('registration.print-layout')
@section('title', 'Kartu Tes')
@section('content')
    <div class="grid"><div><span class="label">Peserta</span><span class="value">{{ $registration->full_name }}</span></div><div><span class="label">Nomor peserta</span><span class="value">{{ $registration->applicant_card_number ?: $registration->registration_number }}</span></div></div>
    <table><thead><tr><th>Tes</th><th>Jadwal</th><th>Lokasi / Petunjuk</th></tr></thead><tbody>
    @foreach($bookings as $booking)
        <tr><td>{{ collect($registration->configuredTests())->firstWhere('id', $booking->admission_test_id)['name'] ?? $booking->admissionTest->name }}</td><td>{{ $booking->session->starts_at->format('d M Y H:i') }}–{{ $booking->session->ends_at->format('H:i') }}</td><td>{{ $booking->session->location }}<br>{{ $booking->session->instructions }}</td></tr>
    @endforeach
    </tbody></table><p>Cetak ulang kartu setelah mengubah jadwal. Hadir sesuai sesi yang tersimpan di portal pendaftar.</p>
@endsection
