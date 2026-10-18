<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Port;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoProbeResultDto;

/**
 * Проверка health sidecar LLM (GET {orchestrator_base_url}/health → {ok:true}).
 * Редиректы не следуются.
 */
interface SchedulePhotoSidecarHealthProbeInterface
{
    /**
     * Возвращает ok=true только при успешном health с телом {ok:true}.
     */
    public function checkHealth(): SchedulePhotoProbeResultDto;
}
