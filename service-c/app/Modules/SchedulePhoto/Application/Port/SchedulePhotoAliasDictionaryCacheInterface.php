<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Port;

/**
 * Сброс кеша словаря OCR→канон после записи файла.
 *
 * Lookup-порт этот метод не видит: сброс нужен только writer.
 */
interface SchedulePhotoAliasDictionaryCacheInterface
{
    /**
     * Сбрасывает кеш map после дозаписи файла словаря.
     */
    public function clearCache(): void;
}
