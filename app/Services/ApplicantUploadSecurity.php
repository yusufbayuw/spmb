<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ApplicantUploadSecurity
{
    public function inspect(string $path): array
    {
        $disk = Storage::disk(ApplicantFileStorage::PRIVATE_DISK);

        if (! $disk->exists($path)) {
            throw ValidationException::withMessages([
                'file' => 'File upload tidak ditemukan pada private storage.',
            ]);
        }

        $absolutePath = $disk->path($path);
        $size = (int) $disk->size($path);
        $maxBytes = ((int) config('spmb.uploads.max_kb', 5120)) * 1024;

        if ($size <= 0 || $size > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => 'Ukuran file tidak valid atau melebihi batas keamanan.',
            ]);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => 'Ekstensi file tidak diizinkan.',
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($absolutePath) ?: 'application/octet-stream');
        $allowedMimes = (array) config('spmb.uploads.allowed_mimes', []);

        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => "Isi file tidak sesuai format yang diizinkan ({$mime}).",
            ]);
        }

        $expectedMime = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        };

        if ($mime !== $expectedMime) {
            throw ValidationException::withMessages([
                'file' => 'Ekstensi dan isi file tidak konsisten.',
            ]);
        }

        $this->validateSignature($absolutePath, $mime);
        $malwareStatus = $this->scanMalware($absolutePath);

        return [
            'mime_type' => $mime,
            'size' => $size,
            'sha256' => hash_file('sha256', $absolutePath),
            'malware_scan_status' => $malwareStatus,
            'security_scanned_at' => now(),
        ];
    }

    private function validateSignature(string $absolutePath, string $mime): void
    {
        if ($mime === 'application/pdf') {
            $handle = fopen($absolutePath, 'rb');
            $header = $handle ? fread($handle, 5) : false;

            if (is_resource($handle)) {
                fclose($handle);
            }

            if ($header !== '%PDF-') {
                throw ValidationException::withMessages([
                    'file' => 'Signature PDF tidak valid.',
                ]);
            }
        } else {
            $imageInfo = @getimagesize($absolutePath);

            if (! is_array($imageInfo)) {
                throw ValidationException::withMessages([
                    'file' => 'File gambar tidak dapat divalidasi.',
                ]);
            }
        }

        $sample = file_get_contents($absolutePath, false, null, 0, min(65536, filesize($absolutePath) ?: 65536));

        if (is_string($sample) && preg_match('/<\?(?:php|=)|<script\b/i', $sample)) {
            throw ValidationException::withMessages([
                'file' => 'File mengandung pola executable/script yang tidak diizinkan.',
            ]);
        }
    }

    private function scanMalware(string $absolutePath): string
    {
        $required = (bool) config('spmb.uploads.require_malware_scan', false);
        $binaryName = (string) config('spmb.uploads.clamav_binary', 'clamscan');
        $binary = (new ExecutableFinder())->find($binaryName);

        if (! $binary) {
            if ($required) {
                throw ValidationException::withMessages([
                    'file' => 'Antivirus server tidak tersedia. Upload ditolak untuk menjaga keamanan data.',
                ]);
            }

            return 'unavailable';
        }

        $process = new Process([$binary, '--no-summary', $absolutePath]);
        $process->setTimeout((float) config('spmb.uploads.clamav_timeout', 30));
        $process->run();

        if ($process->getExitCode() === 0) {
            return 'clean';
        }

        if ($process->getExitCode() === 1) {
            throw ValidationException::withMessages([
                'file' => 'File terdeteksi sebagai malware dan ditolak.',
            ]);
        }

        if ($required) {
            throw ValidationException::withMessages([
                'file' => 'Pemindaian antivirus gagal. Upload ditolak.',
            ]);
        }

        return 'scan_error';
    }
}
