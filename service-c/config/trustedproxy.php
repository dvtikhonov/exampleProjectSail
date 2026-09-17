<?php

declare(strict_types=1);

/**
 * Trusted proxies for X-Forwarded-* (and related) headers.
 *
 * Direct requests to :8083 without a reverse proxy do not need trust.
 * Through nginx-gateway / Docker private networks — yes.
 *
 * Override with TRUSTED_PROXIES (CSV of IPs/CIDRs, or "*").
 */
$trustedProxies = env('TRUSTED_PROXIES');

return [
    'proxies' => ($trustedProxies === null || $trustedProxies === '')
        ? [
            '127.0.0.1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]
        : $trustedProxies,
];
