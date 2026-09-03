<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\DTO\Max\AiAccessStatusDto;
use App\DTO\Max\MaxUserIdentity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API управления доступом AI к базе для роли max_manager.
 */
class AdminAiAccessController extends Controller
{
    public function __construct(
        private readonly MaxAiAccessServiceInterface $maxAiAccessService,
        private readonly AuthenticatedMaxUserResolverInterface $maxUserResolver,
    ) {}

    /**
     * Возвращает текущий статус доступа AI.
     */
    public function show(): JsonResponse
    {
        $status = $this->maxAiAccessService->getStatus(now());

        return response()->json($this->statusPayload($status));
    }

    /**
     * Переключает доступ AI для текущего max_manager и возвращает новый статус.
     */
    public function toggle(Request $request): JsonResponse
    {
        $status = $this->maxAiAccessService->toggle(
            new MaxUserIdentity(
                maxUserId: $this->maxUserResolver->identity()->maxUserId,
            ),
            now(),
        );

        return response()->json($this->statusPayload($status));
    }

    /**
     * Приводит DTO статуса к API-ответу.
     *
     * @return array{enabled: bool, active_max_user_id: ?int, expires_at: ?string}
     */
    private function statusPayload(AiAccessStatusDto $status): array
    {
        return [
            'enabled' => $status->enabled,
            'active_max_user_id' => $status->activeMaxUserId,
            'expires_at' => $status->expiresAt,
        ];
    }
}
