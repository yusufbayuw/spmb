<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $applicationName }}
</x-mail::header>
</x-slot>

# Yth. Bapak/Ibu Pendaftar,

Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda di {{ $applicationName }}.

Silakan gunakan tombol berikut untuk membuat kata sandi baru.

<x-mail::button :url="$url">
Atur Ulang Kata Sandi
</x-mail::button>

Tautan ini berlaku selama {{ $expiresInMinutes }} menit. Demi keamanan akun, jangan bagikan tautan ini kepada pihak lain.

Apabila Anda tidak mengajukan permintaan ini, Anda tidak perlu melakukan tindakan apa pun. Kata sandi Anda tetap tidak berubah.

Hormat kami,<br>
{{ $applicationName }}

<x-slot:subcopy>
Apabila tombol **Atur Ulang Kata Sandi** tidak dapat digunakan, salin dan tempel tautan berikut pada peramban Anda:<br>
<span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $applicationName }}. Seluruh hak cipta dilindungi.
</x-mail::footer>
</x-slot>
</x-mail::layout>
