<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\DTO\Max\AiAccessStatusDto;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Models\Max\MaxUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API управления доступом AI к базе для роли max_manager.
 */
class AdminAiAccessController extends Controller
{
    public function __construct(
        private readonly MaxAiAccessServiceInterface $maxAiAccessService,
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
        try {
            $status = $this->maxAiAccessService->toggle(
                $this->manager($request),
                now(),
            );
        } catch (FoodDomainException $exception) {
            return $this->domainError($exception);
        }

        return response()->json($this->statusPayload($status));
    }

    /**
     * Текущий аутентифицированный менеджер MAX из запроса.
     */
    private function manager(Request $request): MaxUser
    {
        /** @var MaxUser $manager */
        $manager = $request->user();

        return $manager;
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

    /**
     * JSON-ответ с сообщением доменного исключения.
     */
    private function domainError(FoodDomainException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
        ], $exception->statusCode());
    }
}
