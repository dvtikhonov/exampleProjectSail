<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Enums\Food\Review\FoodOrderAdminRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Разрешает PhotoText API только при активном AI-доступе у max_manager
 * (`max_users.ai_access_until` > now).
 */
class EnsurePhotoTextAiAccess
{
    private const string DENIED_MESSAGE = 'Доступ AI к базе не разрешён.';

    public function __construct(
        private readonly MaxAiAccessServiceInterface $maxAiAccessService,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
    ) {}

    /**
     * Проверяет, что есть ровно один активный AI-доступ у пользователя с ролью max_manager.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $status = $this->maxAiAccessService->getStatus(now());

        if (! $status->enabled || $status->activeMaxUserId === null) {
            return response()->json([
                'message' => self::DENIED_MESSAGE,
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $this->foodOrderAdminRepository->hasActiveRole(
            $status->activeMaxUserId,
            FoodOrderAdminRole::MaxManager,
        )) {
            return response()->json([
                'message' => self::DENIED_MESSAGE,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
