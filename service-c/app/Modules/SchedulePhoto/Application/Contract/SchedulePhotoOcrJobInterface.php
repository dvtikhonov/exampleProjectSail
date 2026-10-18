<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Contract;

use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;

/**
 * Очередной handshake OCR и перевод прогона в failed при падении job.
 */
interface SchedulePhotoOcrJobInterface
{
    /**
     * Handshake OCR в queue job (sidecar start).
     *
     * @throws SchedulePhotoDomainException
     */
    public function performQueuedOcr(string $runId): void;

    /**
     * Помечает прогон failed (timeout / падение job).
     * Меняет только pending и ocr_running. matched, applying и applied не затирает:
     * UPDATE … WHERE status IN (pending, ocr_running); 0 строк — тихий пропуск.
     *
     * @param  string|null  $reason  текст ошибки handshake; пусто — общая формулировка timeout
     */
    public function failQueuedOcr(string $runId, ?string $reason = null): void;
}
