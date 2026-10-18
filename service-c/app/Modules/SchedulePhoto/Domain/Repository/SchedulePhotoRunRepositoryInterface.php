<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Domain\Repository;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunCreateDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;

/**
 * Хранение прогонов SchedulePhoto (Eloquent — только в реализации).
 */
interface SchedulePhotoRunRepositoryInterface
{
    /**
     * Создаёт прогон из domain-DTO.
     */
    public function create(SchedulePhotoRunCreateDto $dto): SchedulePhotoRunRecord;

    /**
     * Находит прогон по UUID.
     */
    public function findById(string $runId): ?SchedulePhotoRunRecord;

    /**
     * Сравнивает и записывает: UPDATE … WHERE id = ? AND status IN (ожидаемые).
     * null — ни одна строка не изменилась (статус уже другой или прогона нет).
     * Вызывающий решает: HTTP-конфликт 409 или тихий пропуск (failQueuedOcr).
     *
     * @param  list<SchedulePhotoRunStatus>  $expectedStatuses  непустой список
     */
    public function update(
        string $runId,
        SchedulePhotoRunPatchDto $dto,
        array $expectedStatuses,
    ): ?SchedulePhotoRunRecord;
}
