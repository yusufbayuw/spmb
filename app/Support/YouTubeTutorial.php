<?php

namespace App\Support;

final class YouTubeTutorial
{
    public static function videoId(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        if ($parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $path = $parts['path'] ?? '';
        $id = null;

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $id = ltrim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = $query['v'] ?? null;
            } elseif (preg_match('~^/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})/?$~D', $path, $matches)) {
                $id = $matches[1];
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) ? $id : null;
    }

    public static function embedUrl(?string $url): ?string
    {
        $id = self::videoId($url);

        return $id ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
    }

    public static function watchUrl(?string $url): ?string
    {
        $id = self::videoId($url);

        return $id ? 'https://www.youtube.com/watch?v='.$id : null;
    }
}
