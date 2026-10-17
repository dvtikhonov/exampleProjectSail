<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Repositories;

use App\Modules\SchedulePhoto\Contracts\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Enums\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Models\SchedulePhotoRun;

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
    public function create(array $attributes): SchedulePhotoRunRecord
    {
        $status = $attributes['status'] ?? SchedulePhotoRunStatus::Pending;
        if ($status instanceof SchedulePhotoRunStatus) {
            $status = $status->value;
        }

        $model = SchedulePhotoRun::query()->create([
            'id' => $attributes['id'],
            'status' => $status,
            'base_url' => $attributes['base_url'],
            'date_from' => $attributes['date_from'],
            'date_to' => $attributes['date_to'],
            'restaurant_id' => $attributes['restaurant_id'],
            'category_ids' => $attributes['category_ids'],
            'created_by_max_user_id' => $attributes['created_by_max_user_id'],
            'photo_path' => $attributes['photo_path'] ?? null,
            'match_result' => $attributes['match_result'] ?? null,
            'discarded' => $attributes['discarded'] ?? [],
            'error_message' => $attributes['error_message'] ?? null,
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
    public function update(string $runId, array $attributes): SchedulePhotoRunRecord
    {
        $model = SchedulePhotoRun::query()->findOrFail($runId);

        $payload = [];
        if (array_key_exists('status', $attributes)) {
            $status = $attributes['status'];
            $payload['status'] = $status instanceof SchedulePhotoRunStatus
                ? $status->value
                : $status;
        }
        foreach (['photo_path', 'match_result', 'discarded', 'error_message'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $payload[$key] = $attributes[$key];
            }
        }

        if ($payload !== []) {
            $model->fill($payload);
            $model->save();
        }

        return $this->mapper->toRecord($model->fresh() ?? $model);
    }
}
