<?php

namespace App\Services;

use App\Models\User;

class PortalDestinationService
{
    public function pathFor(User $user): ?string
    {
        if (! $user->is_active) {
            return null;
        }

        if ($user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])) {
            return '/admin';
        }

        if ($user->hasRole('pendaftar')) {
            return '/pendaftar';
        }

        return null;
    }

    public function profilePathFor(User $user): ?string
    {
        return match ($this->pathFor($user)) {
            '/admin' => '/admin',
            '/pendaftar' => '/pendaftar/profile',
            default => null,
        };
    }

    public function intendedUrlIsAllowed(User $user, ?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        $path = parse_url((string) $url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])) {
            return str_starts_with($path, '/admin')
                || in_array($path, ['/dashboard', '/profile'], true);
        }

        if ($user->hasRole('pendaftar')) {
            return str_starts_with($path, '/pendaftar')
                || str_starts_with($path, '/registration/')
                || str_starts_with($path, '/files/applicant/')
                || in_array($path, ['/dashboard', '/profile'], true);
        }

        return false;
    }
}
