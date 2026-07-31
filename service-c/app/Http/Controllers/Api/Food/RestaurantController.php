<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Models\Max\MaxUser;
use App\Services\Food\Menu\MenuQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API списка ресторанов и меню для MAX mini-app.
 */
class RestaurantController extends Controller
{
    public function __construct(
        private readonly MenuQueryService $menuQueryService,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
    ) {}

    /**
     * Возвращает список активных ресторанов.
     */
    public function index(): JsonResponse
    {
        $restaurants = $this->menuQueryService->listActiveRestaurants();

        return response()->json([
            'restaurants' => array_map(
                static fn ($restaurant): array => $restaurant->toArray(),
                $restaurants,
            ),
        ]);
    }

    /**
     * Возвращает меню выбранного ресторана.
     *
     * Query include_unavailable=1 — только для max_manager (ручной заказ).
     */
    public function menu(Request $request, int $restaurant): JsonResponse
    {
        try {
            $menu = $this->menuQueryService->getRestaurantMenu(
                $restaurant,
                $this->shouldIncludeUnavailableDishes($request),
            );
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'menu' => $menu->toArray(),
        ]);
    }

    /**
     * Разрешает показ недоступных блюд только max_manager при явном запросе.
     */
    private function shouldIncludeUnavailableDishes(Request $request): bool
    {
        if (! $request->boolean('include_unavailable')) {
            return false;
        }

        $maxUser = $request->user();

        if (! $maxUser instanceof MaxUser) {
            return false;
        }

        return $this->foodOrderAdminRepository->hasActiveRole(
            $maxUser->max_user_id,
            FoodOrderAdminRole::MaxManager,
        );
    }
}
