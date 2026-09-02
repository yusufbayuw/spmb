<h2>{{ $announcement->title ?: 'Pengumuman Hasil SPMB' }}</h2>
<p>Yth. {{ $announcement->registration->user->name }},</p>
<p>Hasil seleksi untuk <strong>{{ $announcement->registration->full_name }}</strong> telah dipublikasikan.</p>
@if($announcement->message)<p>{{ $announcement->message }}</p>@endif
<p>Silakan masuk ke dashboard SPMB untuk melihat hasil lengkap.</p>
