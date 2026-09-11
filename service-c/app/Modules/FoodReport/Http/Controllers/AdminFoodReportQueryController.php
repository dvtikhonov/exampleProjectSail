<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodReport\Contracts\FoodReportQueryServiceInterface;
use App\Modules\FoodReport\Http\Requests\RevenueReportRequest;
use App\Modules\FoodReport\Http\Requests\TopDishesReportRequest;
use Illuminate\Http\JsonResponse;

/**
 * JSON API отчётов Food для max_manager (выручка и топ блюд, только confirmed).
 */
class AdminFoodReportQueryController extends Controller
{
    public function __construct(
        private readonly FoodReportQueryServiceInterface $queryService,
    ) {}

    /**
     * Выручка по дням за период.
     */
    public function revenue(RevenueReportRequest $request): JsonResponse
    {
        $report = $this->queryService->revenue($request->toFilterDto());

        return response()->json($report->toArray());
    }

    /**
     * Топ позиций по дням за период.
     */
    public function topDishes(TopDishesReportRequest $request): JsonResponse
    {
        $report = $this->queryService->topDishes(
            $request->toFilterDto(),
            $request->limitPerDay(),
        );

        return response()->json($report->toArray());
    }
}
