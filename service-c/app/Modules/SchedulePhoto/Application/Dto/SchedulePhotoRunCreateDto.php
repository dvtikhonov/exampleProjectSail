<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;

/**
 * Команда создания прогона SchedulePhoto (camelCase; snake_case — только в Eloquent-репозитории).
 */
readonly class SchedulePhotoRunCreateDto
{
    /**
     * @param  list<int>  $categoryIds
     * @param  list<SchedulePhotoDiscardedRowDto>  $discarded
     */
    public function __construct(
        public string $id,
        public SchedulePhotoRunStatus $status,
        public string $baseUrl,
        public string $dateFrom,
        public string $dateTo,
        public int $restaurantId,
        public array $categoryIds,
        public int $createdByMaxUserId,
        public ?string $photoPath = null,
        public ?SchedulePhotoScheduleResultDto $matchResult = null,
        public array $discarded = [],
        public ?string $errorMessage = null,
    ) {}
}
