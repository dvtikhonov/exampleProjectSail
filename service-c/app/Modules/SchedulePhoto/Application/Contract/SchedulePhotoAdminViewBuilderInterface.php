<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Contract;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoAdminRunViewDto;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;

/**
 * Admin view прогона: подсказки discarded и catalog_scope / catalog_error.
 */
interface SchedulePhotoAdminViewBuilderInterface
{
    /**
     * Собирает view-DTO для сериализации в admin JSON.
     *
     * Сбой сборки каталога не маскируется пустым scope: catalogScope = null,
     * текст исключения — в catalogError.
     */
    public function build(SchedulePhotoRunRecord $run): SchedulePhotoAdminRunViewDto;
}
