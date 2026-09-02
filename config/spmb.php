<?php

return [
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
];
