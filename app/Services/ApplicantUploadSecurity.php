<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ApplicantUploadSecurity
{
    public function inspect(string $path, array $formats = ['pdf', 'jpg', 'jpeg', 'png']): array
    {
        if (str_starts_with($path, '/') || str_contains($path, '\\') || preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\x00-\x1f]~', $path)) {
            throw ValidationException::withMessages(['file' => 'Lokasi file tidak valid.']);
        }

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
        $allowedExtensions = in_array('jpg', $formats, true) ? array_merge($formats, ['jpeg']) : $formats;

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => 'Ekstensi file tidak diizinkan.',
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($absolutePath) ?: 'application/octet-stream');
        $allowedMimes = (array) config('spmb.uploads.allowed_mimes', []);

        if ($extension === 'docx' && in_array('docx', $formats, true)) {
            $this->validateDocx($absolutePath);
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            $allowedMimes[] = $mime;
        }

        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => "Isi file tidak sesuai format yang diizinkan ({$mime}).",
            ]);
        }

        $expectedMime = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        };

        if ($mime !== $expectedMime) {
            throw ValidationException::withMessages([
                'file' => 'Ekstensi dan isi file tidak konsisten.',
            ]);
        }

        if ($extension !== 'docx') {
            $this->validateSignature($absolutePath, $mime);
        }
        $malwareStatus = $this->scanMalware($absolutePath);

        return [
            'mime_type' => $mime,
            'size' => $size,
            'sha256' => hash_file('sha256', $absolutePath),
            'malware_scan_status' => $malwareStatus,
            'security_scanned_at' => now(),
        ];
    }

    private function validateDocx(string $path): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Struktur DOCX tidak valid.']);
        }
        try {
            if ($zip->numFiles > 1000 || $zip->locateName('[Content_Types].xml') === false || $zip->locateName('word/document.xml') === false) {
                throw ValidationException::withMessages(['file' => 'Struktur DOCX tidak valid.']);
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $total += $entry['size'];
                if ($total > 25 * 1024 * 1024 || str_contains($entry['name'], '..') || preg_match('/vbaProject|embeddings\/|activeX\//i', $entry['name'])) {
                    throw ValidationException::withMessages(['file' => 'DOCX mengandung konten aktif atau melebihi batas ekstraksi.']);
                }
                if (str_ends_with($entry['name'], '.xml') || str_ends_with($entry['name'], '.rels')) {
                    $xml = $zip->getFromIndex($i);
                    if (preg_match('/macroEnabled|<!DOCTYPE|<!ENTITY/i', $xml)) {
                        throw ValidationException::withMessages(['file' => 'Konten DOCX tidak diizinkan.']);
                    }
                }
            }
            $xml = $zip->getFromName('word/document.xml');
            $document = new \DOMDocument;
            if (! @$document->loadXML($xml, LIBXML_NONET) || ($document->documentElement?->localName !== 'document' || $document->documentElement?->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main')) {
                throw ValidationException::withMessages(['file' => 'Dokumen Word tidak valid.']);
            }
        } finally {
            $zip->close();
        }
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
        $binary = (new ExecutableFinder)->find($binaryName);

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
