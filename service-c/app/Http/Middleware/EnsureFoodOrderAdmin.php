<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

/**
 * Проверяет, что аутентифицированный MaxUser имеет хотя бы одну из требуемых
 * активных ролей администратора заказов.
 */
class EnsureFoodOrderAdmin
{
    public function __construct(
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
    ) {}

    /**
     * Проверяет активную роль администратора заказов у пользователя.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if ($roles === []) {
            return response()->json([
                'message' => 'Invalid admin role.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $adminRoles = [];

        foreach ($roles as $role) {
            try {
                $adminRoles[] = FoodOrderAdminRole::from($role);
            } catch (ValueError) {
                return response()->json([
                    'message' => 'Invalid admin role.',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $maxUser = $request->user();

        if (! $maxUser instanceof MaxUser) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        foreach ($adminRoles as $adminRole) {
            if ($this->foodOrderAdminRepository->hasActiveRole($maxUser->max_user_id, $adminRole)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Доступ запрещён.',
        ], Response::HTTP_FORBIDDEN);
    }
}
