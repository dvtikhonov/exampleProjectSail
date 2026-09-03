<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\Menu\DishAvailabilityScheduleServiceInterface;
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
        $grid = $this->scheduleService->getGrid(
            $request->restaurantId(),
            $request->categoryId(),
            $request->dateFrom(),
            $request->dateTo(),
        );

        return response()->json($grid->toArray());
    }

    /**
     * Пакетное сохранение графика доступности.
     */
    public function sync(SyncDishAvailabilityScheduleRequest $request): JsonResponse
    {
        $this->scheduleService->syncSchedule($request->toDto());

        return response()->json([
            'message' => 'График производства блюд сохранён.',
        ]);
    }
}
