<?php

namespace App\Services;

class PushEndpointPolicy
{
    public function allows(string $endpoint): bool
    {
        $url = parse_url($endpoint);
        if (! is_array($url) || strtolower((string) ($url['scheme'] ?? '')) !== 'https'
            || isset($url['user']) || isset($url['pass'])
            || (isset($url['port']) && (int) $url['port'] !== 443)) {
            return false;
        }

        $host = strtolower(rtrim((string) ($url['host'] ?? ''), '.'));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false
            || ! str_contains($host, '.') || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal') || $host === 'localhost') {
            return false;
        }

        foreach (config('spmb.readiness.push_hosts', []) as $candidate) {
            $allowed = strtolower(trim((string) $candidate));
            if ($allowed === $host || (str_starts_with($allowed, '*.')
                && str_ends_with($host, substr($allowed, 1))
                && strlen($host) > strlen($allowed) - 1)) {
                return true;
            }
        }

        return false;
    }
}
