<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Models\MaxUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

/**
 * Проверяет, что аутентифицированный MaxUser имеет активную роль администратора заказов.
 */
class EnsureFoodOrderAdmin
{
    public function __construct(
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        try {
            $adminRole = FoodOrderAdminRole::from($role);
        } catch (ValueError) {
            return response()->json([
                'message' => 'Invalid admin role.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $maxUser = $request->user();

        if (! $maxUser instanceof MaxUser) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->foodOrderAdminRepository->hasActiveRole($maxUser->max_user_id, $adminRole)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
