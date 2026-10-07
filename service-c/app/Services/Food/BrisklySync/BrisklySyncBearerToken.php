<?php

declare(strict_types=1);

namespace App\Services\Food\BrisklySync;

/**
 * Нормализация Bearer Briskly перед кэшем и HTTP Authorization.
 */
final class BrisklySyncBearerToken
{
    /**
     * Убирает пробелы и случайный префикс «Bearer » из DevTools.
     */
    public static function normalize(string $raw): string
    {
        $token = trim($raw);
        if ($token === '') {
            return '';
        }

        if (preg_match('/^Bearer\s+/i', $token) === 1) {
            $token = trim((string) preg_replace('/^Bearer\s+/i', '', $token));
        }

        return $token;
    }

    /**
     * JWT истёк по payload.exp (без проверки подписи). Не-JWT — false.
     */
    public static function isExpired(string $token, ?int $now = null): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if (! is_string($payloadJson) || $payloadJson === '') {
            return true;
        }

        $payload = json_decode($payloadJson, true);
        if (! is_array($payload) || ! isset($payload['exp']) || ! is_numeric($payload['exp'])) {
            return false;
        }

        $nowTs = $now ?? time();

        return (int) $payload['exp'] <= $nowTs + 30;
    }
}
