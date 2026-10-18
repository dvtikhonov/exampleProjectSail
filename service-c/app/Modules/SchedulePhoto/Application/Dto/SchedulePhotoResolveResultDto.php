<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Dto;

use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;

/**
 * Результат resolve-discarded: прогон после CAS + опциональное предупреждение о словаре.
 *
 * aliasPersistError заполняется только при soft-fail записи алиаса после успешного update.
 */
readonly class SchedulePhotoResolveResultDto
{
    public function __construct(
        public SchedulePhotoRunRecord $run,
        public ?string $aliasPersistError = null,
    ) {}
}
