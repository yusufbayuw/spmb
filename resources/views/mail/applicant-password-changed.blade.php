<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $applicationName }}
</x-mail::header>
</x-slot>

# Yth. Bapak/Ibu Pendaftar,

Kata sandi akun Anda di {{ $applicationName }} telah berhasil diubah.

Apabila perubahan ini dilakukan oleh Anda, tidak diperlukan tindakan lebih lanjut.

Apabila Anda tidak merasa melakukan perubahan kata sandi, segera hubungi administrator {{ $applicationName }} untuk mendapatkan bantuan.

Hormat kami,<br>
{{ $applicationName }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $applicationName }}. Seluruh hak cipta dilindungi.
</x-mail::footer>
</x-slot>
</x-mail::layout>
