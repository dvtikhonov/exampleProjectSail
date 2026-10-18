<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Contract;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoOcrCallbackDto;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;

/**
 * Колбэк sidecar после vision OCR.
 */
interface SchedulePhotoOcrCallbackInterface
{
    /**
     * Колбэк sidecar: entries → schedule/match на base_url прогона → matched/issues.
     *
     * @throws SchedulePhotoDomainException
     */
    public function completeOcr(SchedulePhotoOcrCallbackDto $dto): SchedulePhotoRunRecord;
}
