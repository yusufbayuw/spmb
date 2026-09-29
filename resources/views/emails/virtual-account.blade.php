<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('spmb.portal.name', config('app.name', 'SPMB')) }}
</x-mail::header>
</x-slot>

# Virtual Account Pendaftaran SPMB

Yth. {{ $payment->registration->user->name }},

Data calon peserta **{{ $payment->registration->full_name }}** telah tervalidasi. Silakan gunakan informasi berikut untuk melakukan pembayaran biaya pendaftaran.

Nomor registrasi: **{{ $payment->registration->registration_number }}**

@if ($payment->virtualAccount?->bank)
Bank: **{{ $payment->virtualAccount->bank }}**
@endif
Virtual Account: **{{ $payment->va_number }}**
@if (! is_null($payment->amount))
Nominal: **Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}**
@endif

Setelah pembayaran dilakukan, unggah bukti pembayaran melalui dashboard agar dapat diperiksa oleh petugas.

<x-mail::button :url="url('/pendaftar')">
Buka Dashboard SPMB
</x-mail::button>

Hormat kami,<br>
{{ config('spmb.portal.name', config('app.name', 'SPMB')) }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('spmb.portal.name', config('app.name', 'SPMB')) }}. Seluruh hak cipta dilindungi.
</x-mail::footer>
</x-slot>
</x-mail::layout>
