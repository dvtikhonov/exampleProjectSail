<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\UseCase\Ocr;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoOcrCallbackInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoRunAccessInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoOcrCallbackDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleEntryDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Domain\Repository\SchedulePhotoRunRepositoryInterface;
use Throwable;

/**
 * Колбэк sidecar: entries → schedule/match на base_url прогона → matched/issues.
 */
final class SchedulePhotoOcrCompleter implements SchedulePhotoOcrCallbackInterface
{
    public function __construct(
        private readonly SchedulePhotoRunRepositoryInterface $runs,
        private readonly SchedulePhotoRunAccessInterface $access,
        private readonly SchedulePhotoPhotoTextClientInterface $photoText,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function completeOcr(SchedulePhotoOcrCallbackDto $dto): SchedulePhotoRunRecord
    {
        $run = $this->runs->findById($dto->runId);
        if ($run === null) {
            throw new SchedulePhotoDomainException('Прогон SchedulePhoto не найден.', 404);
        }

        if ($run->status !== SchedulePhotoRunStatus::OcrRunning) {
            throw new SchedulePhotoDomainException('Устаревший callback OCR.', 409);
        }

        $errorText = is_string($dto->error) ? trim($dto->error) : '';
        $hasError = $errorText !== '';
        $hasEntries = $dto->entries !== null;

        if ($hasError === $hasEntries) {
            throw new SchedulePhotoDomainException('Нужны либо entries, либо error.', 422);
        }

        if ($hasError) {
            return $this->access->updateOrConflict(
                $dto->runId,
                SchedulePhotoRunPatchDto::change(
                    status: SchedulePhotoRunStatus::Failed,
                    discarded: $dto->discarded,
                    errorMessage: mb_substr($errorText, 0, 2000),
                ),
                [SchedulePhotoRunStatus::OcrRunning],
                'Устаревший callback OCR.',
            );
        }

        /** @var list<array{raw_title: string, dates: list<string>}> $entries */
        $entries = $dto->entries ?? [];

        $matchEntries = [];
        foreach ($entries as $row) {
            $name = trim($row['raw_title']);
            if ($name === '') {
                continue;
            }
            $matchEntries[] = new SchedulePhotoScheduleEntryDto(
                name: $name,
                dates: $row['dates'],
            );
        }

        try {
            $result = $this->photoText->match(
                $run->baseUrl,
                $run->restaurantId,
                $run->categoryIds,
                $run->dateFrom,
                $run->dateTo,
                $matchEntries,
            );
        } catch (Throwable $exception) {
            return $this->access->updateOrConflict(
                $dto->runId,
                SchedulePhotoRunPatchDto::change(
                    status: SchedulePhotoRunStatus::Failed,
                    discarded: $dto->discarded,
                    errorMessage: mb_substr($exception->getMessage(), 0, 2000),
                ),
                [SchedulePhotoRunStatus::OcrRunning],
                'Устаревший callback OCR.',
            );
        }

        return $this->access->updateOrConflict(
            $dto->runId,
            SchedulePhotoRunPatchDto::change(
                status: SchedulePhotoRunStatus::Matched,
                matchResult: $result,
                discarded: $dto->discarded,
                errorMessage: null,
            ),
            [SchedulePhotoRunStatus::OcrRunning],
            'Устаревший callback OCR.',
        );
    }
}
