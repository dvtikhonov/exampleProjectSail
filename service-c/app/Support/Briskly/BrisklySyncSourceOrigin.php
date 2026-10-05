<?php

declare(strict_types=1);

namespace App\Support\Briskly;

/**
 * Разбор origin source VPS для Briskly sync из MAX_MINI_APP_URL (без Illuminate).
 */
final class BrisklySyncSourceOrigin
{
    private function __construct() {}

    /**
     * Origin (scheme + host [+ port]) из URL mini-app; path вроде /max-app отбрасывается.
     */
    public static function fromMiniAppUrl(string $miniAppUrl): string
    {
        $trimmed = trim($miniAppUrl);
        if ($trimmed === '') {
            return '';
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = (string) $parts['host'];
        if ($scheme === '' || $host === '') {
            return '';
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    /**
     * Remote source: origin задан и его host не совпадает с host APP_URL.
     */
    public static function isRemote(string $sourceBaseUrl, string $appUrl): bool
    {
        $sourceHost = self::hostOf($sourceBaseUrl);
        if ($sourceHost === '') {
            return false;
        }

        $appHost = self::hostOf($appUrl);

        return $appHost === '' || strcasecmp($sourceHost, $appHost) !== 0;
    }

    private static function hostOf(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }

        $host = parse_url($trimmed, PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }
}
