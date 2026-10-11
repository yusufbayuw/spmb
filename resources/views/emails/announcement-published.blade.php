<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ app(\App\Services\AdmissionTerminology::class)->portalName() }}
</x-mail::header>
</x-slot>

# {{ app(\App\Services\AdmissionTerminology::class)->announcementDisplayTitle($announcement->title) }}

Yth. {{ $announcement->registration->user->name }},

Hasil seleksi untuk calon peserta **{{ $announcement->registration->full_name }}** telah dipublikasikan.

@if ($announcement->message)
{{ $announcement->message }}
@endif

Silakan masuk ke dashboard untuk melihat hasil dan tindak lanjut secara lengkap.

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
