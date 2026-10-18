<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Contract;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoUiOriginConfigDto;

/**
 * Сборка allow-list origin’ов для UI (без localhost, с лейблами Docker/VPS).
 */
interface SchedulePhotoUiOriginConfigBuilderInterface
{
    /**
     * Собирает default и опции селекта для admin config-эндпоинта.
     */
    public function build(): SchedulePhotoUiOriginConfigDto;
}
