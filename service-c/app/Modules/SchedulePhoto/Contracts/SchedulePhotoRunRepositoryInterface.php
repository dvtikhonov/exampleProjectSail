<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

use App\Modules\SchedulePhoto\DTO\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Enums\SchedulePhotoRunStatus;

/**
 * Хранение прогонов SchedulePhoto (Eloquent — только в реализации).
 */
interface SchedulePhotoRunRepositoryInterface
{
    /**
     * @param  array{
     *     id: string,
     *     status: SchedulePhotoRunStatus|string,
     *     base_url: string,
     *     date_from: string,
     *     date_to: string,
     *     restaurant_id: int,
     *     category_ids: list<int>,
     *     created_by_max_user_id: int,
     *     photo_path?: string|null,
     *     match_result?: array<string, mixed>|null,
     *     discarded?: list<array{raw_title: string, reason: string, dates?: list<string>}>,
     *     error_message?: string|null
     * }  $attributes
     */
    public function create(array $attributes): SchedulePhotoRunRecord;

    /**
     * Находит прогон по UUID.
     */
    public function findById(string $runId): ?SchedulePhotoRunRecord;

    /**
     * @param  array{
     *     status?: SchedulePhotoRunStatus|string,
     *     photo_path?: string|null,
     *     match_result?: array<string, mixed>|null,
     *     discarded?: list<array{raw_title: string, reason: string, dates?: list<string>}>,
     *     error_message?: string|null
     * }  $attributes
     */
    public function update(string $runId, array $attributes): SchedulePhotoRunRecord;
}
