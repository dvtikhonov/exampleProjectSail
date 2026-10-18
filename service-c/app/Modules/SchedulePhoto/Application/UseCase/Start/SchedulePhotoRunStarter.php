<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\UseCase\Start;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoAdminRunInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoIdGeneratorInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoOcrQueueInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoStoreInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoReadinessCheckerInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoRunAccessInterface;
use App\Modules\SchedulePhoto\Domain\Repository\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoReadinessResultDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunCreateDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoStartRunDto;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Services\Food\PhotoText\PhotoTextScheduleWindow;
use InvalidArgumentException;
use Throwable;

/**
 * Старт прогона SchedulePhoto: readiness → фото → create → OCR queue.
 */
final class SchedulePhotoRunStarter implements SchedulePhotoAdminRunInterface
{
    public function __construct(
        private readonly SchedulePhotoReadinessCheckerInterface $readiness,
        private readonly SchedulePhotoRunRepositoryInterface $runs,
        private readonly SchedulePhotoRunAccessInterface $access,
        private readonly SchedulePhotoOcrQueueInterface $ocrQueue,
        private readonly SchedulePhotoPhotoStoreInterface $photoStore,
        private readonly SchedulePhotoIdGeneratorInterface $idGenerator,
        private readonly SchedulePhotoCatalogScopeBuilderInterface $catalogScope,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function start(SchedulePhotoStartRunDto $dto): SchedulePhotoRunRecord
    {
        $ready = $this->readiness->check($dto->baseUrl);
        if (! $ready->ok) {
            throw new SchedulePhotoDomainException($this->readinessFailureMessage($ready), 422);
        }

        $dateFrom = $dto->dateFrom;
        $dateTo = $this->dateToFrom($dateFrom);
        $runId = $this->idGenerator->generate();

        // Scope каталога выбранного origin — до постановки job (fail-fast без sidecar).
        $this->catalogScope->build($dto->baseUrl, $dto->restaurantId, $dto->categoryIds);

        $photoPath = $this->photoStore->storeFromLocalPath($runId, $dto->photoAbsolutePath);

        try {
            $run = $this->runs->create(new SchedulePhotoRunCreateDto(
                id: $runId,
                status: SchedulePhotoRunStatus::Pending,
                baseUrl: $dto->baseUrl,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                restaurantId: $dto->restaurantId,
                categoryIds: $dto->categoryIds,
                createdByMaxUserId: $dto->createdByMaxUserId,
                photoPath: $photoPath,
                discarded: [],
            ));
        } catch (Throwable $exception) {
            $this->deletePhotoWhenRunMissing($runId, $photoPath);

            throw $exception;
        }

        try {
            $this->ocrQueue->dispatch($runId);
        } catch (Throwable $exception) {
            $message = $this->dispatchFailureMessage($exception);
            $this->runs->update(
                $runId,
                SchedulePhotoRunPatchDto::change(
                    status: SchedulePhotoRunStatus::Failed,
                    errorMessage: $message,
                ),
                [SchedulePhotoRunStatus::Pending],
            );

            throw new SchedulePhotoDomainException($message, 503);
        }

        return $this->runs->findById($runId) ?? $run;
    }

    /**
     * {@inheritDoc}
     */
    public function getForAdmin(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord
    {
        return $this->access->findOwnedOrFail($runId, $actorMaxUserId);
    }

    /**
     * Строка не создалась — убрать файл. Если строка есть или это неизвестно, файл не трогать.
     */
    private function deletePhotoWhenRunMissing(string $runId, string $photoPath): void
    {
        try {
            if ($this->runs->findById($runId) !== null) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        try {
            $this->photoStore->delete($photoPath);
        } catch (Throwable) {
            // Исходная ошибка create важнее сбоя очистки файла.
        }
    }

    private function dispatchFailureMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        if ($message === '') {
            $message = 'Не удалось поставить прогон OCR в очередь.';
        }

        return mb_substr($message, 0, 2000);
    }

    private function dateToFrom(string $dateFrom): string
    {
        try {
            return PhotoTextScheduleWindow::dateToFrom($dateFrom);
        } catch (InvalidArgumentException) {
            throw new SchedulePhotoDomainException('Некорректная дата начала графика.', 422);
        }
    }

    private function readinessFailureMessage(SchedulePhotoReadinessResultDto $ready): string
    {
        $parts = [];
        if (! $ready->aiAccessOk) {
            $parts[] = $ready->aiAccessMessage;
        }
        if (! $ready->photoTextOk) {
            $parts[] = $ready->photoTextMessage;
        }
        if (! $ready->sidecarOk) {
            $parts[] = $ready->sidecarMessage;
        }

        return $parts !== []
            ? implode(' ', $parts)
            : 'SchedulePhoto: окружение не готово к OCR.';
    }
}
