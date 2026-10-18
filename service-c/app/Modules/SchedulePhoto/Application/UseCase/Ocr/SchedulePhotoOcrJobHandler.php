<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\UseCase\Ocr;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoOcrJobInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoOcrStartDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesReaderInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoOcrOrchestratorInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoStoreInterface;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Domain\Repository\SchedulePhotoRunRepositoryInterface;
use Throwable;

/**
 * Очередной handshake OCR и перевод прогона в failed при падении job.
 */
final class SchedulePhotoOcrJobHandler implements SchedulePhotoOcrJobInterface
{
    public function __construct(
        private readonly SchedulePhotoRunRepositoryInterface $runs,
        private readonly SchedulePhotoOcrOrchestratorInterface $ocrOrchestrator,
        private readonly SchedulePhotoPhotoStoreInterface $photoStore,
        private readonly SchedulePhotoAliasesReaderInterface $aliasesReader,
        private readonly SchedulePhotoCatalogScopeBuilderInterface $catalogScope,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function performQueuedOcr(string $runId): void
    {
        $run = $this->runs->findById($runId);
        if ($run === null || $run->status !== SchedulePhotoRunStatus::Pending) {
            return;
        }

        $claimed = $this->runs->update(
            $runId,
            SchedulePhotoRunPatchDto::change(
                status: SchedulePhotoRunStatus::OcrRunning,
                errorMessage: null,
            ),
            [SchedulePhotoRunStatus::Pending],
        );
        if ($claimed === null) {
            return;
        }

        try {
            $this->runOcrHandshake($runId);
        } catch (SchedulePhotoDomainException $exception) {
            $this->failQueuedOcr($runId, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->failQueuedOcr($runId, $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function failQueuedOcr(string $runId, ?string $reason = null): void
    {
        $run = $this->runs->findById($runId);
        if ($run === null) {
            return;
        }

        if ($run->status !== SchedulePhotoRunStatus::OcrRunning
            && $run->status !== SchedulePhotoRunStatus::Pending) {
            return;
        }

        $message = trim((string) $reason);
        if ($message === '') {
            $message = 'OCR job failed or timed out.';
        }

        $this->runs->update(
            $runId,
            SchedulePhotoRunPatchDto::change(
                status: SchedulePhotoRunStatus::Failed,
                errorMessage: mb_substr($message, 0, 2000),
            ),
            [
                SchedulePhotoRunStatus::Pending,
                SchedulePhotoRunStatus::OcrRunning,
            ],
        );
    }

    /**
     * Handshake sidecar POST /schedule-ocr.
     *
     * @throws SchedulePhotoDomainException
     */
    private function runOcrHandshake(string $runId): void
    {
        $run = $this->get($runId);
        if ($run->photoPath === null || $run->photoPath === '') {
            throw new SchedulePhotoDomainException('У прогона нет сохранённого фото.', 422);
        }

        $scope = $this->catalogScope->build($run->baseUrl, $run->restaurantId, $run->categoryIds);
        $photoKey = $this->photoStore->relativeKey($run->photoPath);

        $this->ocrOrchestrator->start(new SchedulePhotoOcrStartDto(
            runId: $run->id,
            photoKey: $photoKey,
            dateFrom: $run->dateFrom,
            dateTo: $run->dateTo,
            restaurantId: $run->restaurantId,
            categoryIds: $run->categoryIds,
            catalogScope: $scope,
            aliasesMarkdown: $this->aliasesReader->readMarkdown(),
            photoMimeType: $this->photoStore->mimeType($run->photoPath),
        ));
    }

    /**
     * Возвращает прогон или кидает 404-доменную ошибку.
     *
     * @throws SchedulePhotoDomainException
     */
    private function get(string $runId): SchedulePhotoRunRecord
    {
        $run = $this->runs->findById($runId);
        if ($run === null) {
            throw new SchedulePhotoDomainException('Прогон SchedulePhoto не найден.', 404);
        }

        return $run;
    }
}
