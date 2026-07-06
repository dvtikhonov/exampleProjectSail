<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\DishAvailabilityScheduleServiceInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\Admin\ShowDishAvailabilityScheduleRequest;
use App\Http\Requests\Food\Admin\SyncDishAvailabilityScheduleRequest;
use Illuminate\Http\JsonResponse;

/**
 * API графика доступности блюд для MAX mini-app (menu_manager).
 */
class AdminDishAvailabilityController extends Controller
{
    public function __construct(
        private readonly DishAvailabilityScheduleServiceInterface $scheduleService,
    ) {}

    /**
     * Сетка доступности блюд по датам.
     */
    public function show(ShowDishAvailabilityScheduleRequest $request): JsonResponse
    {
        try {
            $grid = $this->scheduleService->getGrid(
                $request->restaurantId(),
                $request->categoryId(),
                $request->dateFrom(),
                $request->dateTo(),
            );
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json($grid->toArray());
    }

    /**
     * Пакетное сохранение графика доступности.
     */
    public function sync(SyncDishAvailabilityScheduleRequest $request): JsonResponse
    {
        try {
            $this->scheduleService->syncSchedule($request->toDto());
        } catch (FoodDomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        return response()->json([
            'message' => 'График доступности сохранён.',
        ]);
    }
}
