<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Menu\MenuQueryServiceInterface;
use App\Contracts\Food\PhotoText\PhotoTextManualOrderPlacementServiceInterface;
use App\DTO\Food\Shared\RestaurantSummaryDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\PhotoText\PhotoTextAgentOrderRequest;
use App\Http\Requests\Food\PhotoText\PhotoTextCatalogRequest;
use Illuminate\Http\JsonResponse;

/**
 * HTTP API агента Cursor: каталог ресторана, точный матч имён и оформление ручного заказа.
 */
class PhotoTextOrderController extends Controller
{
    public function __construct(
        private readonly MenuQueryServiceInterface $menuQueryService,
        private readonly PhotoTextManualOrderPlacementServiceInterface $placementService,
    ) {}

    /**
     * Активные рестораны: id и name для однозначного выбора restaurant_id.
     */
    public function restaurants(): JsonResponse
    {
        $restaurants = $this->menuQueryService->listActiveRestaurants();

        return response()->json([
            'restaurants' => array_map(
                static fn (RestaurantSummaryDto $restaurant): array => [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                ],
                $restaurants,
            ),
        ]);
    }

    /**
     * Категории и блюда только запрошенного ресторана, включая недоступные.
     */
    public function catalog(PhotoTextCatalogRequest $request): JsonResponse
    {
        $menu = $this->menuQueryService->getRestaurantMenu(
            $request->restaurantId(),
            includeUnavailable: true,
        );

        return response()->json([
            'catalog' => $menu->toArray(),
        ]);
    }

    /**
     * Точная сверка канонических имён в рамках restaurant_id.
     */
    public function match(PhotoTextAgentOrderRequest $request): JsonResponse
    {
        $result = $this->placementService->match(
            $request->customerQuery(),
            $request->restaurantId(),
            $request->items(),
        );

        return response()->json($result->toArray());
    }

    /**
     * Сверка и оформление matched; пустой matched — 422.
     */
    public function store(PhotoTextAgentOrderRequest $request): JsonResponse
    {
        $result = $this->placementService->place(
            $request->customerQuery(),
            $request->orderDate(),
            $request->restaurantId(),
            $request->items(),
        );

        if ($result->orderId === null) {
            return response()->json($result->toArray(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($result->toArray(), JsonResponse::HTTP_CREATED);
    }
}
