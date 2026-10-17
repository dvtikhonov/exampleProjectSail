<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Services;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Shared\ClockInterface;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextProbeInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoReadinessCheckerInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoSidecarHealthProbeInterface;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoReadinessResultDto;

/**
 * Готовность к LLM OCR: активный AI max_manager + probe PhotoText + health sidecar.
 */
final class SchedulePhotoReadinessChecker implements SchedulePhotoReadinessCheckerInterface
{
    private const string AI_DENIED = 'Доступ AI к базе не разрешён. Включите «Вкл. доступ AI».';

    public function __construct(
        private readonly MaxAiAccessServiceInterface $maxAiAccess,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdmins,
        private readonly ClockInterface $clock,
        private readonly SchedulePhotoPhotoTextProbeInterface $photoTextProbe,
        private readonly SchedulePhotoSidecarHealthProbeInterface $sidecarProbe,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function check(string $baseUrl): SchedulePhotoReadinessResultDto
    {
        $ai = $this->checkAiAccess();
        $photoText = $this->photoTextProbe->probe($baseUrl);
        $sidecar = $this->sidecarProbe->checkHealth();

        return new SchedulePhotoReadinessResultDto(
            ok: $ai['ok'] && $photoText->ok && $sidecar->ok,
            aiAccessOk: $ai['ok'],
            aiAccessMessage: $ai['message'],
            photoTextOk: $photoText->ok,
            photoTextMessage: $photoText->message,
            sidecarOk: $sidecar->ok,
            sidecarMessage: $sidecar->message,
        );
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkAiAccess(): array
    {
        $status = $this->maxAiAccess->getStatus($this->clock->now());

        if (! $status->enabled || $status->activeMaxUserId === null) {
            return ['ok' => false, 'message' => self::AI_DENIED];
        }

        if (! $this->foodOrderAdmins->hasActiveRole(
            $status->activeMaxUserId,
            FoodOrderAdminRole::MaxManager,
        )) {
            return ['ok' => false, 'message' => self::AI_DENIED];
        }

        return ['ok' => true, 'message' => 'AI-доступ активен.'];
    }
}
