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
use App\Modules\SchedulePhoto\DTO\SchedulePhotoProbeResultDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoReadinessResultDto;
use App\Modules\SchedulePhoto\Support\SchedulePhotoLocalHosts;

/**
 * Готовность к LLM OCR: флаг AI, probe PhotoText и health sidecar.
 *
 * Локальный хост читает местные max_users и роль max_manager.
 * Удалённый origin местную базу не читает: статус 200 probe (JSON-тело
 * allow-listed origin, без следования редиректам) подтверждает AI,
 * в том числе при пустом списке ресторанов.
 * 403 с известной фразой отказа — только ai_access (PhotoText доступен).
 * Редирект, не-JSON и прочий сбой probe не подтверждают ни AI, ни PhotoText.
 */
final class SchedulePhotoReadinessChecker implements SchedulePhotoReadinessCheckerInterface
{
    private const string AI_DENIED = 'Доступ AI к базе не разрешён. Включите «Вкл. доступ AI».';

    private const string AI_ACTIVE = 'AI-доступ активен.';

    private const string AI_REMOTE_DENIED = 'Доступ AI к базе не разрешён.';

    private const string AI_REMOTE_UNVERIFIED = 'Флаг AI на выбранном сервере не проверен.';

    private const string REMOTE_PHOTOTEXT_AVAILABLE = 'VPS PhotoText доступен.';

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
        $origin = rtrim(trim($baseUrl), '/');
        $local = SchedulePhotoLocalHosts::isLocalOrigin($origin);
        $photoText = $this->photoTextProbe->probe($baseUrl);
        $sidecar = $this->sidecarProbe->checkHealth();
        $ai = $local
            ? $this->checkLocalAiAccess()
            : $this->aiAccessFromRemoteProbe($photoText);

        $photoTextOk = $photoText->ok;
        $photoTextMessage = $photoText->message;
        $aiDenied = ! $local
            && $photoText->status === 403
            && $photoText->message === self::AI_REMOTE_DENIED;
        if ($aiDenied) {
            $photoTextOk = true;
            $photoTextMessage = self::REMOTE_PHOTOTEXT_AVAILABLE;
        }

        return new SchedulePhotoReadinessResultDto(
            ok: $ai['ok'] && $photoTextOk && $sidecar->ok,
            aiAccessOk: $ai['ok'],
            aiAccessMessage: $ai['message'],
            photoTextOk: $photoTextOk,
            photoTextMessage: $photoTextMessage,
            sidecarOk: $sidecar->ok,
            sidecarMessage: $sidecar->message,
        );
    }

    /**
     * Местный флаг: активный AI-доступ у пользователя с ролью max_manager.
     *
     * @return array{ok: bool, message: string}
     */
    private function checkLocalAiAccess(): array
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

        return ['ok' => true, 'message' => self::AI_ACTIVE];
    }

    /**
     * Флаг AI удалённого origin по статусу GET /api/food/phototext/restaurants.
     *
     * @return array{ok: bool, message: string}
     */
    private function aiAccessFromRemoteProbe(SchedulePhotoProbeResultDto $photoText): array
    {
        if ($photoText->status === 200) {
            return ['ok' => true, 'message' => self::AI_ACTIVE];
        }

        if ($photoText->status === 403 && $photoText->message === self::AI_REMOTE_DENIED) {
            return ['ok' => false, 'message' => self::AI_REMOTE_DENIED];
        }

        return ['ok' => false, 'message' => self::AI_REMOTE_UNVERIFIED];
    }
}
