<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BrandMediaService
{
    /** @var list<string> */
    private const ALLOWED_PREFIXES = [
        'units/logos/',
        'branding/',
    ];

    public function disk(): FilesystemAdapter
    {
        return Storage::disk('public');
    }

    public function isAllowed(string $path): bool
    {
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function url(string $path): string
    {
        return route('branding.media', ['path' => ltrim($path, '/')], false);
    }

    /** @return array{name:string,size:int,type:?string,url:string}|null */
    public function fileMetadata(string $path): ?array
    {
        $path = ltrim($path, '/');

        if (! $this->isAllowed($path)) {
            return null;
        }

        $disk = $this->disk();

        try {
            if (! $disk->exists($path)) {
                return null;
            }

            return [
                'name' => basename($path),
                'size' => (int) $disk->size($path),
                'type' => $disk->mimeType($path) ?: null,
                'url' => $this->url($path),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function response(string $path): StreamedResponse
    {
        $path = ltrim($path, '/');

        abort_unless($this->isAllowed($path), 404);

        $disk = $this->disk();

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
