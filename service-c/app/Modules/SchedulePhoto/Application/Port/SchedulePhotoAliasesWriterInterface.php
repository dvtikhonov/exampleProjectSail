<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Port;

/**
 * Дозапись пары OCR→канон в phototext-dish-aliases.md (без БД).
 */
interface SchedulePhotoAliasesWriterInterface
{
    /**
     * Добавляет строку таблицы, если нормализованного ключа OCR ещё нет.
     *
     * @return bool true — записана новая строка; false — пара уже была
     *
     * @throws \RuntimeException если нет существующей копии или запись не прошла во все копии
     */
    public function appendPairIfMissing(string $ocrTitle, string $canonName): bool;

    /**
     * Пишет OCR→канон: новая строка или замена существующей с тем же ключом OCR.
     *
     * @return bool true — файл изменён; false — канон уже совпадал
     *
     * @throws \RuntimeException если нет существующей копии или запись не прошла во все копии
     */
    public function upsertPair(string $ocrTitle, string $canonName): bool;
}
