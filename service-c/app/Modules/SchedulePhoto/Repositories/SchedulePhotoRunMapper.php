<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Repositories;

use App\Modules\SchedulePhoto\DTO\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Enums\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Models\SchedulePhotoRun;

/**
 * Eloquent → {@see SchedulePhotoRunRecord}.
 */
final class SchedulePhotoRunMapper
{
    /**
     * Маппит модель прогона в domain-record.
     */
    public function toRecord(SchedulePhotoRun $model): SchedulePhotoRunRecord
    {
        $status = $model->status;
        if (! $status instanceof SchedulePhotoRunStatus) {
            $status = SchedulePhotoRunStatus::from((string) $status);
        }

        /** @var list<int> $categoryIds */
        $categoryIds = is_array($model->category_ids) ? array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            $model->category_ids,
        )) : [];

        /** @var array<string, mixed>|null $matchResult */
        $matchResult = is_array($model->match_result) ? $model->match_result : null;

        /** @var list<array{raw_title: string, reason: string, dates: list<string>}> $discarded */
        $discarded = [];
        if (is_array($model->discarded)) {
            foreach ($model->discarded as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $dates = [];
                if (is_array($row['dates'] ?? null)) {
                    foreach ($row['dates'] as $date) {
                        if (! is_string($date)) {
                            continue;
                        }
                        $trimmed = trim($date);
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
                            $dates[] = $trimmed;
                        }
                    }
                }
                $discarded[] = [
                    'raw_title' => (string) ($row['raw_title'] ?? ''),
                    'reason' => (string) ($row['reason'] ?? ''),
                    'dates' => array_values(array_unique($dates)),
                ];
            }
        }

        return new SchedulePhotoRunRecord(
            id: (string) $model->id,
            status: $status,
            baseUrl: (string) $model->base_url,
            dateFrom: $this->formatDate($model->date_from),
            dateTo: $this->formatDate($model->date_to),
            restaurantId: (int) $model->restaurant_id,
            categoryIds: $categoryIds,
            createdByMaxUserId: (int) $model->created_by_max_user_id,
            photoPath: $model->photo_path !== null ? (string) $model->photo_path : null,
            matchResult: $matchResult,
            discarded: $discarded,
            errorMessage: $model->error_message !== null ? (string) $model->error_message : null,
        );
    }

    /**
     * Нормализует дату модели к Y-m-d.
     */
    private function formatDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $raw = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $m) === 1) {
            return substr($raw, 0, 10);
        }

        return $raw;
    }
}
