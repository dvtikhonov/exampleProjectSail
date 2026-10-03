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
}
