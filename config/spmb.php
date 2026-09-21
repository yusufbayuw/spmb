<?php

return [
    'operations' => [
        'mode' => env('SPMB_MODE_OPS', 'MIXED'),
    ],

    'portal' => [
        'name' => env('SPMB_PORTAL_NAME', 'Portal Penerimaan Taruna Bakti'),
        'foundation_name' => env('SPMB_FOUNDATION_NAME', 'Yayasan Taruna Bakti'),
        'foundation_website' => env('SPMB_FOUNDATION_WEBSITE'),
        'foundation_email' => env('SPMB_FOUNDATION_EMAIL'),
        'foundation_phone' => env('SPMB_FOUNDATION_PHONE'),
        'foundation_whatsapp' => env('SPMB_FOUNDATION_WHATSAPP'),
        'foundation_address' => env('SPMB_FOUNDATION_ADDRESS', 'Jl. L.L.R.E. Martadinata No. 52, Bandung'),
        'service_hours' => env('SPMB_FOUNDATION_SERVICE_HOURS', 'Hari kerja 08.00–14.00 WIB'),
        'logo_path' => env('SPMB_PORTAL_LOGO_PATH'),
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

    'mail' => [
        'queue' => env('SPMB_MAIL_QUEUE', 'emails'),
    ],

    'notifications' => [
        'queue' => env('SPMB_NOTIFICATION_QUEUE', 'notifications'),
        'polling' => env('SPMB_NOTIFICATION_POLLING', '15s'),
    ],
];
