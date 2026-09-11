<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Contracts;

use App\Modules\FoodReport\DTO\ReportFilterDto;

/**
 * Чтение агрегатов для отчётов Food (orders + order_items).
 */
interface FoodOrderReportRepositoryInterface
{
    /**
     * Дневная выручка: COUNT(*) и SUM(items_total) по confirmed-заказам.
     * Строки отсортированы по date по возрастанию.
     *
     * @return list<array{date: string, orders_count: int, amount: string|float}>
     */
    public function aggregateRevenueByDay(ReportFilterDto $filter): array;

    /**
     * Топ блюд по дням из max_food_order_items (только confirmed).
     *
     * @return list<array{
     *     date: string,
     *     dish_id: int|null,
     *     dish_name: string,
     *     quantity: int,
     *     amount: string|float
     * }>
     */
    public function aggregateTopDishesByDay(ReportFilterDto $filter, int $limitPerDay): array;
}
