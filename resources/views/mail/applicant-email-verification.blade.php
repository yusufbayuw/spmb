<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $applicationName }}
</x-mail::header>
</x-slot>

# Yth. Bapak/Ibu Pendaftar,

Terima kasih telah membuat akun di {{ $applicationName }}.

Untuk mengaktifkan akun dan melanjutkan proses pendaftaran, silakan verifikasi alamat email Anda melalui tombol berikut.

<x-mail::button :url="$url">
Verifikasi Alamat Email
</x-mail::button>

Tautan verifikasi ini berlaku selama {{ $expiresInMinutes }} menit. Jangan bagikan tautan ini kepada pihak lain.

Apabila Anda tidak merasa membuat akun di {{ $applicationName }}, Anda tidak perlu melakukan tindakan apa pun.

Hormat kami,<br>
{{ $applicationName }}

<x-slot:subcopy>
Apabila tombol **Verifikasi Alamat Email** tidak dapat digunakan, salin dan tempel tautan berikut pada peramban Anda:<br>
<span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $applicationName }}. Seluruh hak cipta dilindungi.
</x-mail::footer>
</x-slot>
</x-mail::layout>
