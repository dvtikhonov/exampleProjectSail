<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

use App\Exceptions\Food\FoodDomainException;

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
     * @throws FoodDomainException
     */
    public function storeFromLocalPath(string $runId, string $localAbsolutePath): string;

    /**
     * Относительный ключ файла от корня диска local (`storage/app/private`).
     *
     * `realpath` файла обязан оставаться внутри корня диска.
     *
     * @throws FoodDomainException
     */
    public function relativeKey(string $absolutePath): string;
}
