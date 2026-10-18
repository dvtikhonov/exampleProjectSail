<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Contract;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;

/**
 * Общий доступ к прогону: владелец и CAS-запись.
 *
 * Одни и те же «чужой = 404» и конфликт статуса у жизненного цикла прогона и resolve discarded.
 */
interface SchedulePhotoRunAccessInterface
{
    /**
     * Прогон только создателя. Чужой и отсутствующий дают одну и ту же 404,
     * чтобы UUID не подтверждался разным текстом ошибки.
     *
     * @throws SchedulePhotoDomainException
     */
    public function findOwnedOrFail(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord;

    /**
     * Запись только из ожидаемого статуса. 0 строк — 409, текущее состояние не затирается.
     *
     * @param  list<SchedulePhotoRunStatus>  $expectedStatuses
     *
     * @throws SchedulePhotoDomainException
     */
    public function updateOrConflict(
        string $runId,
        SchedulePhotoRunPatchDto $dto,
        array $expectedStatuses,
        string $conflictMessage,
    ): SchedulePhotoRunRecord;
}
