<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Services\Food\MenuQueryService;
use Illuminate\Http\JsonResponse;

class RestaurantController extends Controller
{
    public function __construct(
        private readonly MenuQueryService $menuQueryService,
    ) {}

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

    public function menu(int $restaurant): JsonResponse
    {
        try {
            $menu = $this->menuQueryService->getRestaurantMenu($restaurant);
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'menu' => $menu->toArray(),
        ]);
    }
}
