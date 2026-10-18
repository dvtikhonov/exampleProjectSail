<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Port;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoOcrStartDto;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;

/**
 * HTTP-оркестратор sidecar OCR (handshake POST /schedule-ocr).
 */
interface SchedulePhotoOcrOrchestratorInterface
{
    /**
     * Стартует vision OCR на sidecar; результат приходит колбэком.
     *
     * @throws SchedulePhotoDomainException sidecar недоступен / отказ handshake
     */
    public function start(SchedulePhotoOcrStartDto $dto): void;
}
