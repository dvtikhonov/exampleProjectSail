<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

use App\Modules\SchedulePhoto\DTO\SchedulePhotoReadinessResultDto;

/**
 * Проверка готовности перед запуском LLM OCR (AI + PhotoText + sidecar).
 */
interface SchedulePhotoReadinessCheckerInterface
{
    /**
     * Проверяет флаг AI (местная база для локального хоста, ответ probe для удалённого origin),
     * probe PhotoText BASE_URL и health sidecar.
     */
    public function check(string $baseUrl): SchedulePhotoReadinessResultDto;
}
