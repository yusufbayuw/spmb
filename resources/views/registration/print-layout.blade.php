<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $registration->registration_number }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#eef2f6;color:#182536;font:14px/1.6 Arial,sans-serif}.sheet{width:min(100% - 32px,790px);margin:32px auto;background:#fff;padding:42px;border-top:7px solid #173c63}.heading{display:flex;justify-content:space-between;gap:24px;border-bottom:1px solid #cdd7e1;padding-bottom:22px}.brand{font-size:13px;letter-spacing:2px;color:#173c63;font-weight:700}h1{font-size:27px;line-height:1.2;margin:8px 0}h2{font-size:17px;margin:24px 0 8px}.muted{color:#617184;font-size:12px}.number{font-family:monospace;font-size:16px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:24px 0}.label{display:block;color:#617184;font-size:12px}.value{font-size:15px;font-weight:600;overflow-wrap:anywhere}.photo{width:90px;height:120px;object-fit:cover;border:1px solid #ccd4dc}table{border-collapse:collapse;width:100%;margin:18px 0}th,td{text-align:left;padding:10px;border-bottom:1px solid #dce3e9;vertical-align:top}th{font-size:12px;color:#52647a;background:#f4f7fa}.note{margin-top:28px;border-top:1px solid #ccd4dc;padding-top:16px}.amount{font-size:25px;font-weight:bold;color:#173c63}.actions{text-align:center;margin:24px}button{background:#173c63;color:white;border:0;border-radius:6px;padding:12px 24px;cursor:pointer}.paid{font-weight:bold;color:#12613f} @page{size:A4;margin:15mm} @media print{body{background:white}.sheet{width:100%;margin:0;padding:15px 0;border-top-width:5px}.actions{display:none}tr,.grid,.heading{break-inside:avoid}thead{display:table-header-group}} @media(max-width:520px){.sheet{padding:22px}.grid{grid-template-columns:1fr}.heading{gap:12px}h1{font-size:23px}th,td{padding:6px;font-size:12px}}
    </style>
</head>
<body>
<main class="sheet">
    <header class="heading"><div><div class="brand">SPMB TARUNA BAKTI</div><h1>@yield('title')</h1><div>{{ isset($receipt) ? $receipt->details['unit'] : $registration->unit->name }}</div><div class="muted">{{ isset($receipt) ? $receipt->details['period'] : $registration->opening?->academic_year }} · {{ isset($receipt) ? ($receipt->details['wave'] ?? '') : $registration->opening?->wave }}</div></div>@yield('header-extra')</header>
    @yield('content')
    <footer class="note muted">Dokumen diterbitkan melalui sistem SPMB. Simpan dokumen ini untuk keperluan pendaftaran.</footer>
</main>
<div class="actions"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
</body>
</html>
