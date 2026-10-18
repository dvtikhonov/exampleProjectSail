<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\UseCase\Start;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoRunAccessInterface;
use App\Modules\SchedulePhoto\Domain\Repository\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;

/**
 * Владение прогоном и CAS-запись поверх репозитория.
 */
final class SchedulePhotoRunAccess implements SchedulePhotoRunAccessInterface
{
    public function __construct(
        private readonly SchedulePhotoRunRepositoryInterface $runs,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findOwnedOrFail(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord
    {
        $run = $this->runs->findById($runId);
        if ($run === null || $run->createdByMaxUserId !== $actorMaxUserId) {
            throw new SchedulePhotoDomainException('Прогон SchedulePhoto не найден.', 404);
        }

        return $run;
    }

    /**
     * {@inheritDoc}
     */
    public function updateOrConflict(
        string $runId,
        SchedulePhotoRunPatchDto $dto,
        array $expectedStatuses,
        string $conflictMessage,
    ): SchedulePhotoRunRecord {
        $updated = $this->runs->update($runId, $dto, $expectedStatuses);
        if ($updated === null) {
            throw new SchedulePhotoDomainException($conflictMessage, 409);
        }

        return $updated;
    }
}
