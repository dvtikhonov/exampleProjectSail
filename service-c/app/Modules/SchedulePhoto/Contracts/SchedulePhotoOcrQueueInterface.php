<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

/**
 * Порт постановки OCR-handshake job в очередь (без Illuminate в Services).
 */
interface SchedulePhotoOcrQueueInterface
{
    /**
     * Ставит RunSchedulePhotoOcrJob для прогона.
     */
    public function dispatch(string $runId): void;
}
