<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Laravel;

use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoIdGeneratorInterface;

/**
 * UUID v4 из random_bytes: версия 4 и variant RFC 4122.
 */
final class RandomBytesSchedulePhotoIdGenerator implements SchedulePhotoIdGeneratorInterface
{
    /**
     * {@inheritDoc}
     */
    public function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
