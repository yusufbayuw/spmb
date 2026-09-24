<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Kartu Pendaftaran</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#f4f7fb;color:#14233b;font-family:Arial,Helvetica,sans-serif}
        main{width:min(92%,680px);margin:64px auto;background:#fff;border:1px solid #dce5ef;border-radius:20px;padding:30px;box-shadow:0 18px 45px rgba(24,54,88,.10)}
        .status{display:inline-flex;padding:8px 12px;border-radius:999px;font-weight:800;font-size:13px}
        .valid{background:#dcfce7;color:#166534}.invalid{background:#fee2e2;color:#991b1b}
        h1{margin:18px 0 8px;font-size:28px}.muted{color:#64748b}
        dl{margin:28px 0 0;display:grid;grid-template-columns:150px 1fr;gap:14px 18px}
        dt{color:#64748b}dd{margin:0;font-weight:700}
        .note{margin-top:26px;padding-top:20px;border-top:1px solid #e2e8f0;font-size:13px;color:#64748b}
        @media(max-width:540px){main{margin:24px auto;padding:22px}dl{grid-template-columns:1fr;gap:4px}dd{margin-bottom:10px}}
    </style>
</head>
<body>
<main>
    <span class="status {{ $isValid ? 'valid' : 'invalid' }}">
        {{ $isValid ? 'KARTU VALID' : 'KARTU TIDAK AKTIF' }}
    </span>

    <h1>Verifikasi Kartu Pendaftaran</h1>
    <p class="muted">Data minimum berikut berasal langsung dari sistem pendaftaran.</p>

    <dl>
        <dt>No. Peserta</dt>
        <dd>{{ $registration->applicant_card_number }}</dd>

        <dt>Nama</dt>
        <dd>{{ $registration->full_name }}</dd>

        <dt>Unit / Institusi</dt>
        <dd>{{ $registration->unit?->name ?? '—' }}</dd>

        @if($registration->opening?->studyProgram)
            <dt>Program Studi</dt>
            <dd>{{ $registration->opening->studyProgram->label() }}</dd>
        @endif

        <dt>Jalur</dt>
        <dd>{{ $registration->pathway?->name ?? '—' }}</dd>

        <dt>Periode</dt>
        <dd>{{ collect([$registration->opening?->academic_year, $registration->opening?->wave])->filter()->implode(' · ') ?: '—' }}</dd>
    </dl>

    <div class="note">
        Halaman ini tidak menampilkan NIK, alamat rumah, atau informasi pribadi sensitif lainnya.
        @if(!$hasPhoto)
            Foto identitas belum tersedia sehingga kartu belum dapat digunakan sebagai kartu final.
        @endif
    </div>
</main>
</body>
</html>
