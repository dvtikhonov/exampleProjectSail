<?php

namespace App\Support;

/**
 * Приводит значения домена/host из .env к формату cookie и Sanctum (без схемы и пути).
 */
final class HostNormalizer
{
    /**
     * @param  string|null  $value  hostname, host:port или ошибочный URL из .env
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        if (str_contains($value, '://')) {
            $parsed = parse_url($value);
            $host = $parsed['host'] ?? null;

            if ($host === null || $host === '') {
                return null;
            }

            $port = $parsed['port'] ?? null;

            return ($port !== null && ! in_array((int) $port, [80, 443], true))
                ? $host.':'.$port
                : $host;
        }

        $value = rtrim($value, '/');

        if (str_contains($value, '/')) {
            $parsed = parse_url('http://'.$value);
            $host = $parsed['host'] ?? null;

            if ($host === null || $host === '') {
                return null;
            }

            $port = $parsed['port'] ?? null;

            return ($port !== null && ! in_array((int) $port, [80, 443], true))
                ? $host.':'.$port
                : $host;
        }

        return $value;
    }
}
