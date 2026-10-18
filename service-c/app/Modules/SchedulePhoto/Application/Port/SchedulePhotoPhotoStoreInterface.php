<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Port;

use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;

/**
 * Временное хранение фото графика для sidecar OCR.
 */
interface SchedulePhotoPhotoStoreInterface
{
    /**
     * Копирует локальный файл во внутреннее хранилище прогона.
     *
     * Расширение имени берётся из MIME содержимого (JPEG → jpg, PNG, WebP).
     * Иной тип или недоступный finfo — 422, файл не записывается.
     *
     * @return string абсолютный путь к сохранённому файлу
     *
     * @throws SchedulePhotoDomainException
     */
    public function storeFromLocalPath(string $runId, string $localAbsolutePath): string;

    /**
     * Относительный ключ файла от корня диска local (`storage/app/private`).
     *
     * `realpath` файла обязан оставаться внутри корня диска.
     *
     * @throws SchedulePhotoDomainException
     */
    public function relativeKey(string $absolutePath): string;

    /**
     * MIME сохранённого файла по расширению имени.
     *
     * Файл уже записан как jpg, png или webp. Неизвестное расширение — image/jpeg.
     * Содержимое и finfo здесь не читаются.
     */
    public function mimeType(string $absolutePath): string;

    /**
     * Удаляет ранее сохранённое фото.
     *
     * Отсутствующий файл — успех. Путь вне диска не удаляется.
     *
     * @throws SchedulePhotoDomainException путь вне хранилища или файл не удалился
     */
    public function delete(string $absolutePath): void;
}
