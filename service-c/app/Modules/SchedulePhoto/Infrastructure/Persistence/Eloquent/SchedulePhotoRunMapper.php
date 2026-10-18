<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedRowDto;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleIssueDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleMatchedDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleResultDto;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoMatchIssueCode;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent\SchedulePhotoRun;

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
            matchResult: $this->hydrateMatchResult($model->match_result),
            discarded: $this->hydrateDiscarded($model->discarded),
            errorMessage: $model->error_message !== null ? (string) $model->error_message : null,
        );
    }

    /**
     * JSON колонки match_result → DTO; пустой/невалидный → null.
     */
    private function hydrateMatchResult(mixed $raw): ?SchedulePhotoScheduleResultDto
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $matched = [];
        $matchedRows = $raw['matched'] ?? [];
        if (is_array($matchedRows)) {
            foreach ($matchedRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $matched[] = new SchedulePhotoScheduleMatchedDto(
                    rawTitle: (string) ($row['raw_title'] ?? ''),
                    dishId: (int) ($row['dish_id'] ?? 0),
                    dishName: (string) ($row['dish_name'] ?? ''),
                    categoryId: (int) ($row['category_id'] ?? 0),
                    categoryName: (string) ($row['category_name'] ?? ''),
                    dates: $this->stringList($row['dates'] ?? null),
                );
            }
        }

        $issues = [];
        $issueRows = $raw['issues'] ?? [];
        if (is_array($issueRows)) {
            foreach ($issueRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = SchedulePhotoMatchIssueCode::tryFrom((string) ($row['code'] ?? ''));
                if ($code === null) {
                    continue;
                }
                $issues[] = new SchedulePhotoScheduleIssueDto(
                    code: $code,
                    message: (string) ($row['message'] ?? ''),
                    rawTitle: (string) ($row['raw_title'] ?? ''),
                    dates: $this->stringList($row['dates'] ?? null),
                );
            }
        }

        $matchedCount = is_numeric($raw['matched_count'] ?? null)
            ? (int) $raw['matched_count']
            : count($matched);

        return new SchedulePhotoScheduleResultDto(
            matchedCount: $matchedCount,
            matched: $matched,
            issues: $issues,
            dateFrom: is_string($raw['date_from'] ?? null) ? $raw['date_from'] : '',
            dateTo: is_string($raw['date_to'] ?? null) ? $raw['date_to'] : '',
            applied: ($raw['applied'] ?? false) === true,
            categoriesApplied: $this->intList($raw['categories_applied'] ?? null),
        );
    }

    /**
     * JSON колонки discarded → list DTO.
     *
     * @return list<SchedulePhotoDiscardedRowDto>
     */
    private function hydrateDiscarded(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $discarded = [];
        foreach ($raw as $row) {
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
            $discarded[] = new SchedulePhotoDiscardedRowDto(
                rawTitle: (string) ($row['raw_title'] ?? ''),
                reason: (string) ($row['reason'] ?? ''),
                dates: array_values(array_unique($dates)),
            );
        }

        return $discarded;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $list = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed !== '') {
                $list[] = $trimmed;
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $list = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $list[] = $value;
            } elseif (is_string($value) && is_numeric($value)) {
                $list[] = (int) $value;
            }
        }

        return array_values(array_unique($list));
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
