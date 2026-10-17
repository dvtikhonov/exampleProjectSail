<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

/**
 * Словарь OCR→канон из phototext-dish-aliases.md (lookup без CRUD).
 */
interface SchedulePhotoAliasDictionaryInterface
{
    /**
     * Канон каталога для сырого OCR-имени или null, если записи нет.
     */
    public function resolveCanon(string $rawTitle): ?string;

    /**
     * Нормализация ключа словаря (регистр, ё/е, кавычки, пробелы).
     */
    public function normalizeKey(string $value): string;

    /**
     * Сбрасывает кеш map после дозаписи файла словаря.
     */
    public function clearCache(): void;
}
