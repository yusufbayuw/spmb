<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ApplicantFileStorage
{
    public const PRIVATE_DISK = 'applicant-private';
    public const LEGACY_PUBLIC_DISK = 'public';

    public function privateDisk(): Filesystem
    {
        return Storage::disk(self::PRIVATE_DISK);
    }

    public function ensurePrivate(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        $private = Storage::disk(self::PRIVATE_DISK);

        if ($private->exists($path)) {
            return true;
        }

        $public = Storage::disk(self::LEGACY_PUBLIC_DISK);

        if (! $public->exists($path)) {
            return false;
        }

        $stream = $public->readStream($path);

        if ($stream === false) {
            throw new RuntimeException("Tidak dapat membaca file publik lama: {$path}");
        }

        try {
            $written = $private->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $written || ! $private->exists($path)) {
            throw new RuntimeException("Gagal memindahkan file pendaftar ke private storage: {$path}");
        }

        $public->delete($path);

        return true;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk(self::PRIVATE_DISK)->delete($path);
        Storage::disk(self::LEGACY_PUBLIC_DISK)->delete($path);
    }
}
