<?php

declare(strict_types=1);

return [
    'store' => [
        'cache_store' => env('FILAMENT_SHIELD_CAPTCHA_CACHE_STORE'),
        'ttl' => (int) env('FILAMENT_SHIELD_CAPTCHA_TTL', 300),
        'max_attempts' => (int) env('FILAMENT_SHIELD_CAPTCHA_MAX_ATTEMPTS', 5),
    ],

    'gd' => [
        'guard_on_boot' => (bool) env('FILAMENT_SHIELD_CAPTCHA_GD_GUARD_ON_BOOT', true),
    ],

    'defaults' => [
        'width' => (int) env('FILAMENT_SHIELD_CAPTCHA_WIDTH', 300),
        'height' => (int) env('FILAMENT_SHIELD_CAPTCHA_HEIGHT', 64),
        'length' => (int) env('FILAMENT_SHIELD_CAPTCHA_LENGTH', 5),
        'mode' => env('FILAMENT_SHIELD_CAPTCHA_MODE', 'custom'),
        'charset' => env('FILAMENT_SHIELD_CAPTCHA_CHARSET', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'),
        'case_sensitive' => (bool) env('FILAMENT_SHIELD_CAPTCHA_CASE_SENSITIVE', false),
        'font_size' => (int) env('FILAMENT_SHIELD_CAPTCHA_FONT_SIZE', 28),
        'format' => env('FILAMENT_SHIELD_CAPTCHA_FORMAT', 'png'),
        'jpeg_quality' => (int) env('FILAMENT_SHIELD_CAPTCHA_JPEG_QUALITY', 85),
        'noise' => [
            'level' => (int) env('FILAMENT_SHIELD_CAPTCHA_NOISE_LEVEL', 1),
            'lines' => (int) env('FILAMENT_SHIELD_CAPTCHA_NOISE_LINES', 2),
            'dots' => (int) env('FILAMENT_SHIELD_CAPTCHA_NOISE_DOTS', 20),
        ],
    ],

    'colors' => [
        'light' => [
            'background' => [255, 255, 255],
            'text' => [31, 41, 55],
        ],
        'dark' => [
            'background' => [24, 24, 27],
            'text' => [250, 250, 250],
        ],
    ],

    'fonts' => [
        'default' => env('FILAMENT_SHIELD_CAPTCHA_FONT', 'noto_sans'),
        'rtl' => env('FILAMENT_SHIELD_CAPTCHA_FONT_RTL', 'vazirmatn'),
        'ltr' => env('FILAMENT_SHIELD_CAPTCHA_FONT_LTR', 'noto_sans'),
        'custom_path' => env('FILAMENT_SHIELD_CAPTCHA_FONT_PATH'),
        'bundled' => [
            'vazirmatn' => 'resources/fonts/Vazirmatn-Regular.ttf',
            'noto_sans' => 'resources/fonts/NotoSans.ttf',
        ],
    ],

    'ui' => [
        'refresh' => [
            'label' => env('FILAMENT_SHIELD_CAPTCHA_REFRESH_LABEL', 'Muat captcha baru'),
            'icon' => env('FILAMENT_SHIELD_CAPTCHA_REFRESH_ICON', 'heroicon-m-arrow-path'),
        ],
    ],
];
