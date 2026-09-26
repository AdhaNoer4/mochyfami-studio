<?php

namespace App\Services\Research\Support;

class DomainExtractor
{
    /**
     * Extract a normalized hostname from an HTTP/HTTPS URL.
     *
     * Strips the scheme, www prefix, paths, query strings and ports.
     * Returns null for any non-HTTP(S) protocol such as javascript:,
     * data: or file:. Fully local — no network requests, no redirects.
     */
    public static function fromUrl(string $url): ?string
    {
        $parsed = parse_url($url);

        $scheme = strtolower(($parsed['scheme'] ?? ''));
        $host = $parsed['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || $host === null || $host === '') {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host === '' ? null : $host;
    }

    /**
     * Determine whether a URL uses an allowed HTTP(S) scheme.
     */
    public static function isHttpUrl(string $url): bool
    {
        return self::fromUrl($url) !== null;
    }
}
