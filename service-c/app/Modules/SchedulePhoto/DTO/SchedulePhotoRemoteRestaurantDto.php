<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\DTO;

/**
 * Ресторан из GET /api/food/phototext/restaurants выбранного origin.
 */
readonly class SchedulePhotoRemoteRestaurantDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
