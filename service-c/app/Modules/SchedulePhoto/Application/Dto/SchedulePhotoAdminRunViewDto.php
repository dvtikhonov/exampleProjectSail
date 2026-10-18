<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;

/**
 * Admin-представление прогона: запись + enriched discarded + scope или ошибка каталога.
 */
readonly class SchedulePhotoAdminRunViewDto
{
    /**
     * @param  list<SchedulePhotoDiscardedHintDto>  $discarded
     * @param  list<SchedulePhotoCatalogScopeDishDto>|null  $catalogScope  null — каталог не собран
     */
    public function __construct(
        public SchedulePhotoRunRecord $run,
        public array $discarded,
        public ?array $catalogScope,
        public ?string $catalogError,
    ) {}
}
