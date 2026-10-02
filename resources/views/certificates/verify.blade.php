<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $certificate->certificate_number }} · {{ $portalName }}</title>
    <style>
        body{font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f3f4f6;color:#111827;margin:0;padding:32px}
        .sheet{max-width:920px;margin:0 auto;background:white;border:1px solid #e5e7eb;border-radius:24px;padding:48px;box-shadow:0 15px 50px rgba(15,23,42,.08)}
        .eyebrow{text-transform:uppercase;letter-spacing:.18em;font-size:12px;color:#6b7280}h1{font-size:36px;margin:12px 0 8px}h2{font-size:24px;margin:8px 0}
        .muted{color:#6b7280}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:32px}.item{padding:16px;border:1px solid #e5e7eb;border-radius:14px}
        .label{font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.08em}.value{margin-top:6px;font-weight:600}.status{display:inline-block;padding:7px 12px;border-radius:999px;font-weight:700}
        .active{background:#dcfce7;color:#166534}.expired,.revoked{background:#fee2e2;color:#991b1b}.footer{display:flex;justify-content:space-between;align-items:end;gap:24px;margin-top:36px;padding-top:28px;border-top:1px solid #e5e7eb}
        .qr{width:130px;height:130px}.actions{max-width:920px;margin:18px auto;text-align:right}.button{background:#111827;color:white;border:0;border-radius:10px;padding:10px 16px;cursor:pointer}
        @media(max-width:700px){body{padding:12px}.sheet{padding:24px}.grid{grid-template-columns:1fr}.footer{align-items:start;flex-direction:column}}@media print{body{background:white;padding:0}.sheet{box-shadow:none;border:0;border-radius:0;max-width:none}.actions{display:none}}
    </style>
</head>
<body>
    @php($status = $certificate->effectiveStatus())
    <div class="actions">
        @if (app(\App\Services\TrainingGovernanceService::class)->certificateArtifactEnabled() || $certificate->artifact_path)
            <a class="button" style="text-decoration:none" href="{{ route('certificates.pdf', ['certificate' => $certificate, 'download' => 1]) }}">Unduh Artifact PDF</a>
        @endif
        <button class="button" onclick="window.print()">Cetak Halaman</button>
    </div>
    <main class="sheet">
        <div class="eyebrow">{{ $portalName }}</div><h1>Bukti Kelulusan Sertifikasi SPMB</h1>
        <p class="muted">Identitas penerima, program, versi, dan nilai pada dokumen ini merupakan snapshot saat sertifikat diterbitkan.</p>

        <div style="margin-top:36px">
            <div class="eyebrow">Diberikan kepada</div>
            <h2>{{ $certificate->recipientName() }}</h2>
            <div class="muted">{{ $certificate->recipientUnit() }} · {{ $certificate->recipientRole() }}</div>
        </div>

        <div style="margin-top:32px">
            <div class="eyebrow">Sertifikasi</div>
            <h2>{{ $certificate->programName() }}</h2>
            <div class="muted">{{ $certificate->programCode() }} · Versi {{ $certificate->programVersion() }}</div>
        </div>

        <div class="grid">
            <div class="item"><div class="label">Nomor Sertifikat</div><div class="value">{{ $certificate->certificate_number }}</div></div>
            <div class="item"><div class="label">Nilai Akhir</div><div class="value">{{ number_format((float) $certificate->score, 2, ',', '.') }}</div></div>
            @if ($certificate->theory_score !== null)
                <div class="item"><div class="label">Nilai Teori</div><div class="value">{{ number_format((float) $certificate->theory_score, 2, ',', '.') }}</div></div>
            @endif
            @if ($certificate->practical_score !== null)
                <div class="item"><div class="label">Nilai Practical</div><div class="value">{{ number_format((float) $certificate->practical_score, 2, ',', '.') }}</div></div>
            @endif
            <div class="item"><div class="label">Tanggal Terbit</div><div class="value">{{ $certificate->issued_at?->timezone(config('app.timezone'))->format('d/m/Y') }}</div></div>
            <div class="item"><div class="label">Berlaku Sampai</div><div class="value">{{ $certificate->expires_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'Tanpa batas waktu' }}</div></div>
        </div>

        <div style="margin-top:28px">
            <span class="status {{ $status }}">{{ match($status) {'active' => 'VALID', 'expired' => 'KEDALUWARSA', 'revoked' => 'DICABUT', default => strtoupper($status)} }}</span>
        </div>

        @if ($status === 'revoked')
            <p class="muted" style="margin-top:12px">Sertifikat ini telah dicabut oleh penerbit. Hubungi pengelola SPMB jika diperlukan verifikasi administratif lebih lanjut.</p>
        @endif

        <div class="footer">
            <div>
                <div class="label">Kode Verifikasi</div>
                <div class="value" style="font-family:monospace">{{ $certificate->verification_code }}</div>
                <p class="muted" style="max-width:560px;font-size:13px">Status pada halaman verifikasi merupakan status terkini. Snapshot identitas dan program tidak berubah saat master data pengguna atau program diperbarui.</p>
                @if ($certificate->artifact_sha256)
                    <div class="label" style="margin-top:12px">SHA-256 Artifact</div>
                    <div class="value" style="font-family:monospace;font-size:11px;word-break:break-all">{{ $certificate->artifact_sha256 }}</div>
                @endif
            </div>
            <img class="qr" src="{{ route('certificates.qr', $certificate) }}" alt="QR verifikasi sertifikat">
        </div>
    </main>
</body>
</html>
