<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Контекст HTTP-запроса к mini-app: локальный ПК vs публичный туннель MAX.
 */
final class MaxAppRequestContext
{
    public static function publicAppUrl(): ?string
    {
        $explicit = trim((string) config('max.public_app_url', ''));

        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }

        $webhookUrl = trim((string) config('max.webhook.url', ''));

        if ($webhookUrl === '') {
            return null;
        }

        $parts = parse_url($webhookUrl);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port;
    }

    public static function requestHost(?Request $request = null): string
    {
        $request ??= request();
        $host = (string) ($request->header('X-Forwarded-Host') ?? $request->getHost());

        return explode(':', $host)[0];
    }

    public static function isPublicTunnelRequest(?Request $request = null): bool
    {
        $publicUrl = self::publicAppUrl();

        if ($publicUrl === null) {
            return false;
        }

        $publicHost = parse_url($publicUrl, PHP_URL_HOST);

        if (! is_string($publicHost) || $publicHost === '') {
            return false;
        }

        return strcasecmp($publicHost, self::requestHost($request)) === 0;
    }

    public static function isLocalDevelopmentRequest(?Request $request = null): bool
    {
        return in_array(self::requestHost($request), ['localhost', '127.0.0.1', 'service-c'], true);
    }
}
