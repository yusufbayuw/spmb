<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PendingAchievementUpload
{
    /**
     * FilePond must load already-stored private files through an authenticated URL.
     * Filament's default fallback to Storage::url() points to the public /storage path.
     *
     * @return array{name: string, size: int, type: string, url: string}|null
     */
    public function uploadedFile(string $path, string|array|null $storedFileNames): ?array
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->is_active || ! $user->isUser()) {
            return null;
        }

        $prefix = 'pre-registration/'.$user->id.'/achievements/';

        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        $filename = substr($path, strlen($prefix));

        if ($this->pathFor($user, $filename) !== $path) {
            return null;
        }

        $disk = Storage::disk(ApplicantFileStorage::PRIVATE_DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        return [
            'name' => is_string($storedFileNames) && $storedFileNames !== ''
                ? basename($storedFileNames)
                : $filename,
            'size' => (int) $disk->size($path),
            'type' => $this->mimeTypeFor($filename),
            'url' => route('files.applicant.pending-achievement-certificate', ['filename' => $filename]),
        ];
    }

    public function pathFor(User $user, string $filename): ?string
    {
        if (
            strlen($filename) > 255
            || str_contains($filename, '..')
            || ! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.(?:pdf|jpe?g|png)\z/iD', $filename)
        ) {
            return null;
        }

        return 'pre-registration/'.$user->id.'/achievements/'.$filename;
    }

    public function mimeTypeFor(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}
