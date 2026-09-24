<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kartu Pendaftaran · {{ $registration->applicant_card_number }}</title>
    <style>
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            margin: 0;
            background: #eef3f8;
            color: #0f274d;
            font-family: Arial, Helvetica, sans-serif;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            padding: 14px;
            background: rgba(255,255,255,.96);
            border-bottom: 1px solid #dbe4ef;
            backdrop-filter: blur(8px);
        }
        .toolbar button {
            appearance: none;
            border: 0;
            border-radius: 10px;
            background: #124f9c;
            color: #fff;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
        }
        .toolbar button.secondary { background: #334155; }
        .a4-page {
            width: min(100%, 210mm);
            min-height: 297mm;
            margin: 24px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            box-shadow: 0 18px 50px rgba(15, 39, 77, .14);
        }
        .card-svg {
            display: block;
            width: min(92vw, 856px);
            height: auto;
            filter: drop-shadow(0 12px 26px rgba(15, 39, 77, .16));
        }
        .hint {
            max-width: 760px;
            margin: -8px auto 28px;
            padding: 0 18px;
            text-align: center;
            color: #5c6c80;
            font-size: 13px;
        }
        @page { size: A4 portrait; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar, .hint { display: none !important; }
            .a4-page {
                width: 210mm;
                height: 297mm;
                min-height: 297mm;
                margin: 0;
                box-shadow: none;
                page-break-after: avoid;
            }
            .card-svg {
                width: 85.6mm;
                height: 53.98mm;
                filter: none;
            }
        }
    </style>
</head>
<body>
@php
    $unitWords = preg_split('/\s+/u', trim($card['unitName'])) ?: [];
    $unitInitials = collect($unitWords)
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
@endphp

<div class="toolbar">
    <button type="button" onclick="window.print()">Cetak / Simpan PDF A4</button>
    <button type="button" class="secondary" onclick="downloadCard('png')">Unduh PNG</button>
    <button type="button" class="secondary" onclick="downloadCard('jpeg')">Unduh JPG</button>
</div>

<main class="a4-page">
<svg
    id="registration-card-svg"
    class="card-svg"
    viewBox="0 0 1011 638"
    xmlns="http://www.w3.org/2000/svg"
    role="img"
    aria-labelledby="card-title card-desc"
>
    <title id="card-title">Kartu Pendaftaran {{ $registration->full_name }}</title>
    <desc id="card-desc">Kartu pendaftaran resmi {{ $card['unitName'] }}.</desc>

    <defs>
        <clipPath id="card-clip">
            <rect x="8" y="8" width="995" height="622" rx="30" />
        </clipPath>
        <linearGradient id="header-gradient" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#06346f"/>
            <stop offset="58%" stop-color="#0b4f9d"/>
            <stop offset="100%" stop-color="#1970c9"/>
        </linearGradient>
        <linearGradient id="footer-gradient" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stop-color="#06346f"/>
            <stop offset="100%" stop-color="#0d5bae"/>
        </linearGradient>
        <pattern id="security-pattern" width="56" height="56" patternUnits="userSpaceOnUse">
            <path d="M0 28 C14 8 42 8 56 28 C42 48 14 48 0 28Z" fill="none" stroke="#4c91d0" stroke-width="1" opacity=".13"/>
        </pattern>
    </defs>

    <g clip-path="url(#card-clip)">
        <rect width="1011" height="638" fill="#f8fbff"/>
        <rect width="1011" height="190" fill="url(#header-gradient)"/>
        <rect y="190" width="1011" height="330" fill="#f8fbff"/>
        <rect y="190" width="1011" height="330" fill="url(#security-pattern)"/>
        <path d="M620 190 C760 260 800 390 1011 430 L1011 190Z" fill="#dfefff" opacity=".55"/>
        <path d="M570 520 C720 450 835 475 1011 520 L1011 638 L570 638Z" fill="#e7f3ff"/>
        <rect y="520" width="650" height="118" fill="url(#footer-gradient)"/>

        @if($card['logoDataUri'])
            <rect x="42" y="32" width="118" height="118" rx="16" fill="#fff" opacity=".10"/>
            <image href="{{ $card['logoDataUri'] }}" x="50" y="40" width="102" height="102" preserveAspectRatio="xMidYMid meet"/>
        @else
            <circle cx="101" cy="91" r="54" fill="#fff" opacity=".13"/>
            <circle cx="101" cy="91" r="46" fill="none" stroke="#fff" stroke-width="3" opacity=".85"/>
            <text x="101" y="103" text-anchor="middle" fill="#fff" font-size="34" font-weight="700">{{ $unitInitials ?: 'SP' }}</text>
        @endif

        <line x1="178" y1="40" x2="178" y2="150" stroke="#fff" stroke-width="2" opacity=".72"/>
        <text x="202" y="64" fill="#d9ebff" font-size="22" font-weight="700" letter-spacing="2">KARTU PENDAFTARAN</text>

        @foreach($card['unitNameLines'] as $index => $line)
            <text x="202" y="{{ 106 + ($index * 34) }}" fill="#fff" font-size="{{ $card['unitNameFontSize'] }}" font-weight="800">{{ $line }}</text>
        @endforeach

        @if($card['unitAddress'])
            <text x="202" y="169" fill="#dcecff" font-size="16">{{ $card['unitAddress'] }}</text>
        @endif

        <g font-family="Arial, Helvetica, sans-serif">
            <text x="62" y="242" fill="#45637f" font-size="17" font-weight="700">No. Peserta</text>
            <text x="278" y="242" fill="#0f274d" font-size="25" font-weight="800">{{ $card['cardNumber'] }}</text>

            <text x="62" y="296" fill="#45637f" font-size="17" font-weight="700">Nama</text>
            @foreach($card['participantNameLines'] as $index => $line)
                <text x="278" y="{{ 296 + ($index * 22) }}" fill="#0f274d" font-size="{{ $card['participantNameFontSize'] }}" font-weight="800">{{ $line }}</text>
            @endforeach

            <text x="62" y="350" fill="#45637f" font-size="17" font-weight="700">Tempat, Tgl Lahir</text>
            <text x="278" y="350" fill="#0f274d" font-size="22" font-weight="700">{{ $card['birth'] }}</text>

            <text x="62" y="404" fill="#45637f" font-size="17" font-weight="700">{{ $card['secondaryLabel'] }}</text>
            @foreach($card['secondaryValueLines'] as $index => $line)
                <text x="278" y="{{ 404 + ($index * 21) }}" fill="#0f274d" font-size="{{ $card['secondaryValueFontSize'] }}" font-weight="700">{{ $line }}</text>
            @endforeach

            <text x="62" y="458" fill="#45637f" font-size="17" font-weight="700">Jalur</text>
            <text x="278" y="458" fill="#0f274d" font-size="22" font-weight="700">{{ $card['pathway'] }}</text>

            <text x="62" y="502" fill="#45637f" font-size="17" font-weight="700">Periode</text>
            <text x="278" y="502" fill="#0f274d" font-size="20" font-weight="700">{{ $card['period'] }}</text>
        </g>

        <rect x="772" y="215" width="194" height="260" rx="14" fill="#fff" stroke="#b7d3ed" stroke-width="3"/>
        <image href="{{ $card['photoDataUri'] }}" x="780" y="223" width="178" height="244" preserveAspectRatio="xMidYMid slice"/>

        <rect x="42" y="534" width="90" height="90" rx="10" fill="#fff"/>
        <image href="{{ $card['verificationQrDataUri'] }}" x="47" y="539" width="80" height="80"/>
        <line x1="158" y1="542" x2="158" y2="613" stroke="#fff" stroke-width="2" opacity=".7"/>
        <text x="182" y="570" fill="#fff" font-size="18" font-weight="700">Scan untuk verifikasi</text>
        <text x="182" y="596" fill="#dcecff" font-size="14">Validasi kartu melalui Portal Penerimaan</text>

        <text x="710" y="558" fill="#45637f" font-size="15">Diterbitkan</text>
        <text x="710" y="586" fill="#0f274d" font-size="21" font-weight="800">{{ $card['issuedDate'] }}</text>
        <line x1="710" y1="600" x2="954" y2="600" stroke="#7ca6cb" stroke-width="1.5"/>
        <text x="710" y="624" fill="#0f274d" font-size="16" font-weight="700">Panitia SPMB</text>
    </g>

    <rect x="8" y="8" width="995" height="622" rx="30" fill="none" stroke="#2b79bd" stroke-width="3"/>
</svg>
</main>

<p class="hint">
    Saat mencetak, gunakan skala 100% / Actual Size agar ukuran kartu tetap 85,6 × 53,98 mm pada kertas A4.
</p>

<script>
async function downloadCard(format) {
    const svg = document.getElementById('registration-card-svg');
    const serializer = new XMLSerializer();
    const source = '<?xml version="1.0" encoding="UTF-8"?>' + serializer.serializeToString(svg);
    const blob = new Blob([source], { type: 'image/svg+xml;charset=utf-8' });
    const blobUrl = URL.createObjectURL(blob);
    const image = new Image();

    image.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = 1011;
        canvas.height = 638;

        const context = canvas.getContext('2d');

        if (format === 'jpeg') {
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
        }

        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(blobUrl);

        const mime = format === 'jpeg' ? 'image/jpeg' : 'image/png';
        const extension = format === 'jpeg' ? 'jpg' : 'png';

        canvas.toBlob((output) => {
            if (!output) {
                return;
            }

            const url = URL.createObjectURL(output);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = @json($card['filenameStem']) + '.' + extension;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            URL.revokeObjectURL(url);
        }, mime, format === 'jpeg' ? 0.94 : undefined);
    };

    image.src = blobUrl;
}
</script>
</body>
</html>
