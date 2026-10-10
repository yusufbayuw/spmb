<?php

return [
    'reset' => [
        // SHA-256 fingerprint from spmb:reset-operational preview. Never set on production.
        'allowed_target' => env('SPMB_RESET_ALLOWED_TARGET'),
    ],

    'operations' => [
        'mode' => env('SPMB_MODE_OPS', 'MIXED'),
    ],

    'portal' => [
        'name' => env('SPMB_PORTAL_NAME', env('APP_NAME', 'SPMB')),
        'foundation_name' => env('SPMB_FOUNDATION_NAME', env('APP_NAME', 'Institusi Pendidikan')),
        'foundation_website' => env('SPMB_FOUNDATION_WEBSITE'),
        'foundation_email' => env('SPMB_FOUNDATION_EMAIL'),
        'foundation_phone' => env('SPMB_FOUNDATION_PHONE'),
        'foundation_whatsapp' => env('SPMB_FOUNDATION_WHATSAPP'),
        'foundation_address' => env('SPMB_FOUNDATION_ADDRESS'),
        'service_hours' => env('SPMB_FOUNDATION_SERVICE_HOURS'),
        'logo_path' => env('SPMB_PORTAL_LOGO_PATH'),
        'theme_color' => env('SPMB_THEME_COLOR', '#2563eb'),
    ],

    'uploads' => [
        'max_kb' => (int) env('SPMB_UPLOAD_MAX_KB', 5120),
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ],
        'require_malware_scan' => (bool) env('SPMB_UPLOAD_REQUIRE_MALWARE_SCAN', false),
        'clamav_binary' => env('SPMB_CLAMAV_BINARY', 'clamscan'),
        'clamav_timeout' => (int) env('SPMB_CLAMAV_TIMEOUT', 30),
    ],

    'certificate_artifact_signing_key' => env('SPMB_CERTIFICATE_ARTIFACT_SIGNING_KEY'),

    'mail' => [
        'queue' => env('SPMB_MAIL_QUEUE', 'emails'),
        'automatic_reminders_enabled' => (bool) env('SPMB_AUTOMATIC_REMINDERS_ENABLED', false),
        'reminder_interval_hours' => (int) env('SPMB_REMINDER_INTERVAL_HOURS', 48),
    ],

    'readiness' => [
        'probes_enabled' => (bool) env('SPMB_READINESS_PROBES_ENABLED', false),
        // HTTPS-only Web Push services. Override only after reviewing provider egress.
        'push_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'SPMB_PUSH_ALLOWED_HOSTS',
            'fcm.googleapis.com,updates.push.services.mozilla.com,*.push.apple.com,web.push.apple.com,*.notify.windows.com,*.wns.windows.com'
        ))))),
    ],

    'notifications' => [
        'queue' => env('SPMB_NOTIFICATION_QUEUE', 'notifications'),
        'polling' => env('SPMB_NOTIFICATION_POLLING', '15s'),
    ],
];
