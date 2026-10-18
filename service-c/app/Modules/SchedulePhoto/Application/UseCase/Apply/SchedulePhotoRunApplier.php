<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\UseCase\Apply;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoRunAccessInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoRunApplyInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Domain\Repository\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\Domain\Service\SchedulePhotoStoredMatchEntries;
use Throwable;

/**
 * Запись графика на origin прогона после human-confirm (matched → applying → applied).
 *
 * Human-confirm = сам вызов apply (POST). Пишет график на base_url прогона;
 * при matched_count = 0 — STOP без записи и без захвата статуса.
 * Перед HTTP PhotoText: matched → applying. Повтор при applying/applied PhotoText не вызывает.
 * Статус applied — только после успешного ответа PhotoText, и только из applying.
 * При сбое PhotoText apply: CAS applying → matched + errorMessage (повторный apply снова вызывает PhotoText).
 * До записи — только прогон создателя.
 */
final class SchedulePhotoRunApplier implements SchedulePhotoRunApplyInterface
{
    public function __construct(
        private readonly SchedulePhotoRunRepositoryInterface $runs,
        private readonly SchedulePhotoRunAccessInterface $access,
        private readonly SchedulePhotoPhotoTextClientInterface $photoText,
        private readonly SchedulePhotoStoredMatchEntries $storedMatchEntries,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function apply(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord
    {
        $run = $this->access->findOwnedOrFail($runId, $actorMaxUserId);
        $this->assertApplyStatus($run);

        $matchResult = $run->matchResult;
        if ($matchResult === null) {
            throw new SchedulePhotoDomainException('У прогона нет результата match.', 422);
        }

        if ($matchResult->matchedCount <= 0 || $matchResult->matched === []) {
            throw new SchedulePhotoDomainException(
                'Нет matched позиций: запись графика остановлена (matched_count = 0).',
                422,
            );
        }

        $entries = $this->storedMatchEntries->fromMatchResult($matchResult);
        if ($entries === []) {
            throw new SchedulePhotoDomainException(
                'Нет matched позиций: запись графика остановлена (matched_count = 0).',
                422,
            );
        }

        $claimed = $this->runs->update(
            $runId,
            SchedulePhotoRunPatchDto::change(
                status: SchedulePhotoRunStatus::Applying,
                errorMessage: null,
            ),
            [SchedulePhotoRunStatus::Matched],
        );
        if ($claimed === null) {
            $this->throwApplyConflict($runId, $actorMaxUserId);
        }

        try {
            $applied = $this->photoText->apply(
                $run->baseUrl,
                $run->restaurantId,
                $run->categoryIds,
                $run->dateFrom,
                $run->dateTo,
                $entries,
            );
        } catch (Throwable $exception) {
            $this->runs->update(
                $runId,
                SchedulePhotoRunPatchDto::change(
                    status: SchedulePhotoRunStatus::Matched,
                    errorMessage: mb_substr($exception->getMessage(), 0, 2000),
                ),
                [SchedulePhotoRunStatus::Applying],
            );

            throw $exception;
        }

        if (! $applied->applied || $applied->matchedCount === 0) {
            $this->runs->update(
                $runId,
                SchedulePhotoRunPatchDto::change(
                    status: SchedulePhotoRunStatus::Matched,
                    errorMessage: null,
                ),
                [SchedulePhotoRunStatus::Applying],
            );

            throw new SchedulePhotoDomainException(
                'Нет matched позиций: запись графика остановлена (matched_count = 0).',
                422,
            );
        }

        $saved = $this->runs->update(
            $runId,
            SchedulePhotoRunPatchDto::change(
                status: SchedulePhotoRunStatus::Applied,
                matchResult: $applied,
                errorMessage: null,
            ),
            [SchedulePhotoRunStatus::Applying],
        );
        if ($saved === null) {
            $this->throwApplyConflict($runId, $actorMaxUserId);
        }

        return $saved;
    }

    /**
     * Повторный confirm при applying/applied не доходит до PhotoText.
     *
     * @throws SchedulePhotoDomainException
     */
    private function assertApplyStatus(SchedulePhotoRunRecord $run): void
    {
        if ($run->status === SchedulePhotoRunStatus::Applied) {
            throw new SchedulePhotoDomainException('График этого прогона уже записан.', 409);
        }

        if ($run->status === SchedulePhotoRunStatus::Applying) {
            throw new SchedulePhotoDomainException('Запись графика уже выполняется.', 409);
        }

        if ($run->status !== SchedulePhotoRunStatus::Matched) {
            throw new SchedulePhotoDomainException(
                'Запись графика доступна только после успешного match (status=matched).',
                409,
            );
        }
    }

    /**
     * 0 строк у захвата matched → applying: статус успел смениться.
     *
     * @throws SchedulePhotoDomainException
     */
    private function throwApplyConflict(string $runId, int $actorMaxUserId): never
    {
        $current = $this->runs->findById($runId);
        if ($current === null || $current->createdByMaxUserId !== $actorMaxUserId) {
            throw new SchedulePhotoDomainException('Прогон SchedulePhoto не найден.', 404);
        }

        if ($current->status === SchedulePhotoRunStatus::Applied) {
            throw new SchedulePhotoDomainException('График этого прогона уже записан.', 409);
        }

        if ($current->status === SchedulePhotoRunStatus::Applying) {
            throw new SchedulePhotoDomainException('Запись графика уже выполняется.', 409);
        }

        throw new SchedulePhotoDomainException(
            'Запись графика доступна только после успешного match (status=matched).',
            409,
        );
    }
}
