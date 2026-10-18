<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Contract;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoStartRunDto;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;

/**
 * Admin-порт прогона: старт и чтение создателя.
 */
interface SchedulePhotoAdminRunInterface
{
    /**
     * Проверяет readiness, сохраняет фото, создаёт run и ставит OCR job.
     *
     * Сбой create пробрасывается как есть. Если строки нет — сохранённое фото удаляется.
     * Сбой постановки в очередь переводит прогон в failed с текстом ошибки (не pending) и отвечает 503.
     *
     * @throws SchedulePhotoDomainException readiness fail / валидация scope / очередь не приняла job
     * @throws \Throwable ошибка create
     */
    public function start(SchedulePhotoStartRunDto $dto): SchedulePhotoRunRecord;

    /**
     * Прогон для admin-чтения: только создатель.
     * Чужой прогон неотличим от отсутствующего (одна и та же 404).
     *
     * @throws SchedulePhotoDomainException
     */
    public function getForAdmin(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord;
}
