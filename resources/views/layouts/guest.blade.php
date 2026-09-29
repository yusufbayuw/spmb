<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('spmb.portal.name', 'SPMB') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @include('pwa.meta')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-gray-100 px-4 py-8">
            <div>
                <a href="/" aria-label="Kembali ke halaman utama SPMB">
                    <x-application-logo class="h-20 w-20 fill-current text-gray-500" />
                </a>
            </div>

            <div class="mt-6 w-full max-w-md overflow-hidden rounded-xl bg-white px-6 py-6 shadow-md sm:px-8">
                {{ $slot }}
            </div>
        </div>

        @include('pwa.client', ['showBanner' => false])
    </body>
</html>
