<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Support\Max\MaxPublicAppUrl;
use Illuminate\Http\Request;

/**
 * Контекст HTTP-запроса к mini-app: локальный ПК vs публичный туннель MAX.
 */
final class MaxAppRequestContext
{
    /**
     * Возвращает публичный базовый URL приложения из конфигурации.
     */
    public static function publicAppUrl(): ?string
    {
        return MaxPublicAppUrl::resolve(
            (string) config('max.public_app_url', ''),
            (string) config('max.webhook.url', ''),
        );
    }

    /**
     * Извлекает хост из запроса с учётом X-Forwarded-Host.
     */
    public static function requestHost(?Request $request = null): string
    {
        $request ??= request();
        $host = (string) ($request->header('X-Forwarded-Host') ?? $request->getHost());

        return explode(':', $host)[0];
    }

    /**
     * Определяет, пришёл ли запрос через публичный туннель MAX.
     */
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

    /**
     * Определяет, выполнен ли запрос с локальной машины разработки.
     */
    public static function isLocalDevelopmentRequest(?Request $request = null): bool
    {
        return in_array(self::requestHost($request), ['localhost', '127.0.0.1', 'service-c'], true);
    }
}
