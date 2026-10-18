<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Domain\Support;

/**
 * Хосты, для которых PhotoText считается локальным (Docker и loopback).
 *
 * Один список для подписи probe и проверки флага AI в readiness.
 */
final class SchedulePhotoLocalHosts
{
    /**
     * @var list<string>
     */
    public const array HOSTS = [
        'localhost',
        '127.0.0.1',
        'host.docker.internal',
    ];

    public static function isLocalOrigin(string $origin): bool
    {
        $host = strtolower((string) (parse_url($origin, PHP_URL_HOST) ?: ''));

        return in_array($host, self::HOSTS, true);
    }
}
