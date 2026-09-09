<?php

declare(strict_types=1);

namespace App\Support\Max;

/**
 * Чистый разбор публичного базового URL mini-app из значений конфигурации (без Illuminate).
 */
final class MaxPublicAppUrl
{
    private function __construct() {}

    /**
     * Возвращает публичный базовый URL из явного значения или origin webhook URL.
     */
    public static function resolve(string $explicitPublicAppUrl, string $webhookUrl): ?string
    {
        $explicit = trim($explicitPublicAppUrl);

        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }

        $webhook = trim($webhookUrl);

        if ($webhook === '') {
            return null;
        }

        $parts = parse_url($webhook);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port;
    }
}
