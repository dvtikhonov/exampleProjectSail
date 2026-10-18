<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent;

use App\Modules\SchedulePhoto\Domain\Repository\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedRowDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunCreateDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent\SchedulePhotoRun;
use InvalidArgumentException;

/**
 * Eloquent-репозиторий прогонов SchedulePhoto.
 */
final class EloquentSchedulePhotoRunRepository implements SchedulePhotoRunRepositoryInterface
{
    public function __construct(
        private readonly SchedulePhotoRunMapper $mapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function create(SchedulePhotoRunCreateDto $dto): SchedulePhotoRunRecord
    {
        $model = SchedulePhotoRun::query()->create([
            'id' => $dto->id,
            'status' => $dto->status->value,
            'base_url' => $dto->baseUrl,
            'date_from' => $dto->dateFrom,
            'date_to' => $dto->dateTo,
            'restaurant_id' => $dto->restaurantId,
            'category_ids' => $dto->categoryIds,
            'created_by_max_user_id' => $dto->createdByMaxUserId,
            'photo_path' => $dto->photoPath,
            'match_result' => $dto->matchResult?->toArray(),
            'discarded' => array_values(array_map(
                static fn (SchedulePhotoDiscardedRowDto $row): array => $row->toArray(),
                $dto->discarded,
            )),
            'error_message' => $dto->errorMessage,
        ]);

        return $this->mapper->toRecord($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(string $runId): ?SchedulePhotoRunRecord
    {
        $model = SchedulePhotoRun::query()->find($runId);

        return $model instanceof SchedulePhotoRun ? $this->mapper->toRecord($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function update(
        string $runId,
        SchedulePhotoRunPatchDto $dto,
        array $expectedStatuses,
    ): ?SchedulePhotoRunRecord {
        $expectedValues = $this->expectedStatusValues($expectedStatuses);
        $payload = $this->persistedAttributes($dto);
        if ($payload === []) {
            return null;
        }

        $affected = SchedulePhotoRun::query()
            ->whereKey($runId)
            ->whereIn('status', $expectedValues)
            ->update($payload);

        if ($affected === 0) {
            return null;
        }

        $model = SchedulePhotoRun::query()->find($runId);

        return $model instanceof SchedulePhotoRun ? $this->mapper->toRecord($model) : null;
    }

    /**
     * @param  list<SchedulePhotoRunStatus>  $expectedStatuses
     * @return list<string>
     */
    private function expectedStatusValues(array $expectedStatuses): array
    {
        if ($expectedStatuses === []) {
            throw new InvalidArgumentException('Ожидаемый статус для обновления прогона обязателен.');
        }

        $values = [];
        foreach ($expectedStatuses as $status) {
            if (! $status instanceof SchedulePhotoRunStatus) {
                throw new InvalidArgumentException('Ожидаемый статус должен быть SchedulePhotoRunStatus.');
            }
            $values[] = $status->value;
        }

        return array_values(array_unique($values));
    }

    /**
     * Значения в том виде, в каком их пишет Eloquent (enum → string, json → строка).
     * Query-builder update касты модели не применяет.
     *
     * @return array<string, mixed>
     */
    private function persistedAttributes(SchedulePhotoRunPatchDto $dto): array
    {
        $fill = [];
        if ($dto->hasStatus()) {
            $fill['status'] = $dto->status;
        }
        if ($dto->hasPhotoPath()) {
            $fill['photo_path'] = $dto->photoPath;
        }
        if ($dto->hasMatchResult()) {
            $fill['match_result'] = $dto->matchResult?->toArray();
        }
        if ($dto->hasDiscarded()) {
            $fill['discarded'] = $dto->discarded === null
                ? null
                : array_values(array_map(
                    static fn (SchedulePhotoDiscardedRowDto $row): array => $row->toArray(),
                    $dto->discarded,
                ));
        }
        if ($dto->hasErrorMessage()) {
            $fill['error_message'] = $dto->errorMessage;
        }
        if ($fill === []) {
            return [];
        }

        $model = new SchedulePhotoRun;
        $model->fill($fill);

        $stored = [];
        $raw = $model->getAttributes();
        foreach (array_keys($fill) as $key) {
            $stored[$key] = $raw[$key] ?? null;
        }

        return $stored;
    }
}
