<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

use App\Modules\SchedulePhoto\DTO\SchedulePhotoProbeResultDto;

/**
 * Серверный probe VPS PhotoText: GET {base_url}/api/food/phototext/restaurants.
 */
interface SchedulePhotoPhotoTextProbeInterface
{
    /**
     * Проверяет доступность PhotoText API на указанном BASE_URL (токен — на сервере).
     */
    public function probe(string $baseUrl): SchedulePhotoProbeResultDto;
}
