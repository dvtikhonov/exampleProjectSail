<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Contract;

use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;

/**
 * Admin-порт записи графика после human-confirm.
 */
interface SchedulePhotoRunApplyInterface
{
    /**
     * Apply только matched после human-confirm (matched_count > 0).
     * Перед HTTP PhotoText статус matched → applying (0 строк — 409, PhotoText не вызывается).
     * Повтор при applying или applied PhotoText не вызывает.
     * Только создатель; чужой прогон неотличим от отсутствующего.
     *
     * @throws SchedulePhotoDomainException
     */
    public function apply(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord;
}
