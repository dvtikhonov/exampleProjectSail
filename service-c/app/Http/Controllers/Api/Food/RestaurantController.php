<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Menu\MenuQueryServiceInterface;
use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\RestaurantMenuRequest;
use Illuminate\Http\JsonResponse;

/**
 * API списка ресторанов и меню для MAX mini-app.
 */
class RestaurantController extends Controller
{
    public function __construct(
        private readonly MenuQueryServiceInterface $menuQueryService,
        private readonly AuthenticatedMaxUserResolverInterface $authenticatedMaxUserResolver,
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
    public function menu(RestaurantMenuRequest $request, int $restaurant): JsonResponse
    {
        $menu = $this->menuQueryService->getRestaurantMenu(
            $restaurant,
            $this->shouldIncludeUnavailableDishes($request),
        );

        return response()->json([
            'menu' => $menu->toArray(),
        ]);
    }

    /**
     * Разрешает показ недоступных блюд только max_manager при явном запросе.
     */
    private function shouldIncludeUnavailableDishes(RestaurantMenuRequest $request): bool
    {
        if (! $request->includeUnavailable()) {
            return false;
        }

        return $this->authenticatedMaxUserResolver
            ->identity()
            ->hasAdminRole(FoodOrderAdminRole::MaxManager);
    }
}
