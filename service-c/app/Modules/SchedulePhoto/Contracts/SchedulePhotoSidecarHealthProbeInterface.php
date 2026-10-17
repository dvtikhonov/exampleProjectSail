<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

use App\Modules\SchedulePhoto\DTO\SchedulePhotoProbeResultDto;

/**
 * Проверка health sidecar LLM (GET {orchestrator_base_url}/health → {ok:true}).
 */
interface SchedulePhotoSidecarHealthProbeInterface
{
    /**
     * Возвращает ok=true только при успешном health с телом {ok:true}.
     */
    public function checkHealth(): SchedulePhotoProbeResultDto;
}
