<?php

namespace App\Http\Controllers;

use App\Services\AppBrandingService;
use Illuminate\Http\JsonResponse;

class PwaManifestController extends Controller
{
    public function __invoke(AppBrandingService $branding): JsonResponse
    {
        $name = $branding->portalName();

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => mb_substr($name, 0, 24),
            'description' => 'Portal penerimaan peserta didik dan mahasiswa.',
            'lang' => 'id-ID',
            'dir' => 'ltr',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f8fafc',
            'theme_color' => $branding->themeColor(),
            'icons' => [
                [
                    'src' => '/images/pwa/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/images/pwa/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/images/pwa/icon-maskable-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                ['name' => 'Portal Pendaftar', 'short_name' => 'Pendaftar', 'url' => '/pendaftar'],
                ['name' => 'Portal Admin', 'short_name' => 'Admin', 'url' => '/admin'],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
}
