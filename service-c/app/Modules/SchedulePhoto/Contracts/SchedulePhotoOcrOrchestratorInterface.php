<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoOcrStartDto;

/**
 * HTTP-оркестратор sidecar OCR (handshake POST /schedule-ocr).
 */
interface SchedulePhotoOcrOrchestratorInterface
{
    /**
     * Стартует vision OCR на sidecar; результат приходит колбэком.
     *
     * @throws FoodDomainException sidecar недоступен / отказ handshake
     */
    public function start(SchedulePhotoOcrStartDto $dto): void;
}
