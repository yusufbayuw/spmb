@extends('registration.print-layout')
@section('title', 'Kuitansi Pembayaran')
@section('header-extra')<div class="paid">LUNAS</div>@endsection
@section('content')
    <h2 class="number">{{ $receipt->number }}</h2>
    <div class="grid">
        <div><span class="label">Diterima untuk peserta</span><span class="value">{{ $receipt->details['participant'] }}</span></div>
        <div><span class="label">Nomor pendaftaran</span><span class="value">{{ $receipt->details['registration_number'] }}</span></div>
        <div><span class="label">Unit / Periode pembayaran</span><span class="value">{{ $receipt->details['unit'] }} · {{ $receipt->details['period'] }}</span></div>
        <div><span class="label">Bank / Virtual Account</span><span class="value">{{ $receipt->details['bank'] ?? '—' }} · {{ $receipt->details['va_number'] ?? '—' }}</span></div>
        <div><span class="label">Tanggal verifikasi</span><span class="value">{{ $receipt->details['verified_at'] ?? '—' }}</span></div>
        <div><span class="label">Kuitansi diterbitkan</span><span class="value">{{ $receipt->issued_at->format('d M Y H:i') }}</span></div>
    </div>
    <table><thead><tr><th>Uraian</th><th>Jumlah</th></tr></thead><tbody><tr><td>Biaya pendaftaran SPMB</td><td class="amount">Rp {{ number_format((float) $receipt->details['amount'], 0, ',', '.') }}</td></tr></tbody></table>
    <p>Pembayaran telah diverifikasi oleh petugas. Kuitansi ini merupakan bukti pembayaran biaya pendaftaran.</p>
@endsection
