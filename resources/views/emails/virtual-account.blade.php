<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ app(\App\Services\AdmissionTerminology::class)->portalName() }}
</x-mail::header>
</x-slot>

# {{ app(\App\Services\AdmissionTerminology::class)->virtualAccountSubject() }}

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
{{ app(\App\Services\AdmissionTerminology::class)->dashboardButton() }}
</x-mail::button>

Hormat kami,<br>
{{ app(\App\Services\AdmissionTerminology::class)->portalName() }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ app(\App\Services\AdmissionTerminology::class)->portalName() }}. Seluruh hak cipta dilindungi.
</x-mail::footer>
</x-slot>
</x-mail::layout>
