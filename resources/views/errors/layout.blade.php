@php
    $isApplicant = request()->is('pendaftar*');
    $isAdmin = request()->is('admin*');
    $contextHome = $isApplicant ? url('/pendaftar') : ($isAdmin ? url('/admin') : url('/'));
    $primaryUrl = $primaryUrl ?? $contextHome;
    $primaryLabel = $primaryLabel ?? 'Kembali ke Beranda';
    $secondaryUrl = $secondaryUrl ?? url('/');
    $secondaryLabel = $secondaryLabel ?? 'Halaman utama';
    $reference = request()->attributes->get('request_id');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#2563eb">
    <title>{{ $status }} — {{ $title }} | SPMB Taruna Bakti</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f6f8fc;
            --surface: rgba(255, 255, 255, .92);
            --text: #172033;
            --muted: #667085;
            --line: #e4e8f0;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --soft: #eef4ff;
            --shadow: 0 24px 64px rgba(15, 23, 42, .10);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1120;
                --surface: rgba(17, 24, 39, .94);
                --text: #f8fafc;
                --muted: #a7b0c0;
                --line: #263247;
                --primary: #60a5fa;
                --primary-hover: #93c5fd;
                --soft: #111d35;
                --shadow: 0 28px 72px rgba(0, 0, 0, .34);
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px 18px;
            background:
                radial-gradient(circle at 20% 20%, rgba(37, 99, 235, .10), transparent 34rem),
                radial-gradient(circle at 85% 80%, rgba(14, 165, 233, .08), transparent 30rem),
                var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .shell { width: min(100%, 680px); }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 22px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .01em;
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: var(--primary);
            color: white;
            box-shadow: 0 8px 20px rgba(37, 99, 235, .22);
        }

        .card {
            padding: clamp(28px, 6vw, 54px);
            border: 1px solid var(--line);
            border-radius: 26px;
            background: var(--surface);
            box-shadow: var(--shadow);
            text-align: center;
            backdrop-filter: blur(12px);
        }

        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 76px;
            height: 38px;
            padding: 0 14px;
            margin-bottom: 22px;
            border-radius: 999px;
            background: var(--soft);
            color: var(--primary);
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .08em;
        }

        h1 {
            margin: 0;
            font-size: clamp(28px, 5vw, 40px);
            line-height: 1.12;
            letter-spacing: -.035em;
        }

        .message {
            max-width: 530px;
            margin: 18px auto 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .hint {
            max-width: 530px;
            margin: 14px auto 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid var(--line);
            border-radius: 12px;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition: transform .15s ease, border-color .15s ease, background .15s ease;
        }

        .button:hover { transform: translateY(-1px); }

        .button-primary {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }

        .button-primary:hover { background: var(--primary-hover); }

        .reference {
            margin: 24px auto 0;
            padding: 12px 14px;
            max-width: 420px;
            border: 1px dashed var(--line);
            border-radius: 12px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.55;
        }

        .reference code {
            display: block;
            margin-top: 4px;
            color: var(--text);
            font: 700 13px ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            word-break: break-all;
        }

        .footer {
            margin-top: 18px;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 520px) {
            .actions { flex-direction: column; }
            .button { width: 100%; }
            .card { border-radius: 20px; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <div class="brand" aria-label="SPMB Taruna Bakti">
            <span class="brand-mark" aria-hidden="true">TB</span>
            <span>SPMB Taruna Bakti</span>
        </div>

        <section class="card" aria-labelledby="error-title">
            <div class="status">{{ $status }}</div>
            <h1 id="error-title">{{ $title }}</h1>
            <p class="message">{{ $message }}</p>

            @if (! empty($hint))
                <p class="hint">{{ $hint }}</p>
            @endif

            <div class="actions">
                <a class="button button-primary" href="{{ $primaryUrl }}">{{ $primaryLabel }}</a>

                @if (! empty($secondaryUrl) && ! empty($secondaryLabel) && $secondaryUrl !== $primaryUrl)
                    <a class="button" href="{{ $secondaryUrl }}">{{ $secondaryLabel }}</a>
                @endif
            </div>

            @if (($showReference ?? false) && filled($reference))
                <div class="reference">
                    Jika masalah berulang, sampaikan kode referensi ini kepada panitia atau administrator.
                    <code>{{ $reference }}</code>
                </div>
            @endif
        </section>

        <div class="footer">Sistem Penerimaan Murid Baru Taruna Bakti</div>
    </main>
</body>
</html>
