<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $portalName }}</title>
</head>
<body style="margin:0;padding:24px;background-color:#f4f6fa;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
    <tr>
        <td style="padding:22px 30px;background:#1d4ed8;color:#ffffff;font-size:19px;font-weight:700;">
            {{ $portalName }}
        </td>
    </tr>
    <tr>
        <td style="padding:24px 30px;font-size:15px;line-height:1.65;overflow-wrap:anywhere;">
            {{-- Content was sanitized using RegistrationRichEmailHtml before rendering. --}}
            {!! $content !!}
        </td>
    </tr>
    <tr>
        <td style="padding:18px 30px;border-top:1px solid #e5e7eb;color:#64748b;font-size:12px;line-height:1.5;">
            Pesan resmi dari {{ $portalName }}. Apabila Anda tidak mengenali pesan ini, hubungi petugas institusi.
        </td>
    </tr>
</table>
</body>
</html>
