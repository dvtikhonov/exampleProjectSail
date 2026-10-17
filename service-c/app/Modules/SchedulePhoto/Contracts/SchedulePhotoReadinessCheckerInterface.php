<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

use App\Modules\SchedulePhoto\DTO\SchedulePhotoReadinessResultDto;

/**
 * Проверка готовности перед запуском LLM OCR (AI + VPS PhotoText + sidecar).
 */
interface SchedulePhotoReadinessCheckerInterface
{
    /**
     * Проверяет AI-доступ, probe PhotoText BASE_URL и health sidecar.
     */
    public function check(string $baseUrl): SchedulePhotoReadinessResultDto;
}
