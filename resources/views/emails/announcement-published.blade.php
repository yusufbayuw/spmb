<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('spmb.portal.name', config('app.name', 'SPMB')) }}
</x-mail::header>
</x-slot>

# {{ $announcement->title ?: 'Pengumuman Hasil SPMB' }}

Yth. {{ $announcement->registration->user->name }},

Hasil seleksi untuk calon peserta **{{ $announcement->registration->full_name }}** telah dipublikasikan.

@if ($announcement->message)
{{ $announcement->message }}
@endif

Silakan masuk ke dashboard untuk melihat hasil dan tindak lanjut secara lengkap.

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
