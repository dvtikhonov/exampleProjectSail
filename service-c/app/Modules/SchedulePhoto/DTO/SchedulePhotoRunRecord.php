<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\DTO;

use App\Modules\SchedulePhoto\Enums\SchedulePhotoRunStatus;

/**
 * Domain-запись прогона SchedulePhoto (без Eloquent).
 */
readonly class SchedulePhotoRunRecord
{
    /**
     * @param  list<int>  $categoryIds
     * @param  array<string, mixed>|null  $matchResult  сериализованный отчёт match/apply
     * @param  list<array{raw_title: string, reason: string, dates?: list<string>}>  $discarded
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
        public ?array $matchResult = null,
        public array $discarded = [],
        public ?string $errorMessage = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'base_url' => $this->baseUrl,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'restaurant_id' => $this->restaurantId,
            'category_ids' => $this->categoryIds,
            'created_by_max_user_id' => $this->createdByMaxUserId,
            'match_result' => $this->matchResult,
            'discarded' => $this->discarded,
            'error_message' => $this->errorMessage,
        ];
    }
}
