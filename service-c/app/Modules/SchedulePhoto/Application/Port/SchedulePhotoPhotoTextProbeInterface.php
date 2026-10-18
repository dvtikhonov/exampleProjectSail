<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Port;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoProbeResultDto;

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
