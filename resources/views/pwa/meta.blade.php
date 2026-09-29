@php
    $branding = app(\App\Services\AppBrandingService::class);
@endphp
<link rel="manifest" href="{{ route('pwa.manifest', absolute: false) }}">
<link rel="apple-touch-icon" sizes="180x180" href="/images/pwa/apple-touch-icon.png">
<meta name="theme-color" content="{{ $branding->themeColor() }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $branding->portalName() }}">
