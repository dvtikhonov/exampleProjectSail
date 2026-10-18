<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Domain\Service;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleEntryDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleResultDto;

/**
 * Разбор сохранённого match_result в entries для повторного match и apply.
 *
 * Чистая функция: matched + issues, без порта и без подмены.
 */
final class SchedulePhotoStoredMatchEntries
{
    /**
     * Восстанавливает entries для schedule/apply из сохранённого отчёта match
     * (matched + issues), чтобы после apply в отчёте остались issues.
     *
     * @return list<SchedulePhotoScheduleEntryDto>
     */
    public function fromMatchResult(SchedulePhotoScheduleResultDto $matchResult): array
    {
        $entries = [];

        foreach ($matchResult->matched as $row) {
            $name = trim($row->dishName !== '' ? $row->dishName : $row->rawTitle);
            if ($name === '') {
                continue;
            }
            $entries[] = new SchedulePhotoScheduleEntryDto(
                name: $name,
                dates: $this->normalizeDates($row->dates),
            );
        }

        foreach ($matchResult->issues as $row) {
            $name = trim($row->rawTitle);
            if ($name === '') {
                continue;
            }
            $entries[] = new SchedulePhotoScheduleEntryDto(
                name: $name,
                dates: $this->normalizeDates($row->dates),
            );
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    public function normalizeDates(mixed $dates): array
    {
        if (! is_array($dates)) {
            return [];
        }

        $normalized = [];
        foreach ($dates as $date) {
            if (! is_string($date)) {
                continue;
            }
            $trimmed = trim($date);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return array_values(array_unique($normalized));
    }
}
