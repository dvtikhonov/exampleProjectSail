<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Repositories;

use App\Enums\Food\Order\OrderStatus;
use App\Models\Food\FoodOrder;
use App\Modules\FoodReport\Contracts\FoodOrderReportRepositoryInterface;
use App\Modules\FoodReport\DTO\ReportFilterDto;
use App\Modules\FoodReport\Enums\ReportDateAxis;
use App\Modules\FoodReport\Http\Requests\FoodReportFilterRequest;
use App\Modules\FoodReport\Models\FoodOrderItem;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent read-репозиторий агрегатов отчётов Food (вариант B, только confirmed).
 */
final class EloquentFoodOrderReportRepository implements FoodOrderReportRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function aggregateRevenueByDay(ReportFilterDto $filter): array
    {
        $dateExpression = $this->ordersDateExpression($filter->dateAxis);

        $rows = FoodOrder::query()
            ->where('status', OrderStatus::Confirmed)
            ->where('restaurant_id', $filter->restaurantId)
            ->whereRaw($dateExpression.' BETWEEN ? AND ?', [$filter->dateFrom, $filter->dateTo])
            ->selectRaw($dateExpression.' as report_day')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(items_total), 0) as amount')
            ->groupBy(DB::raw($dateExpression))
            ->orderBy('report_day', 'asc')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'date' => (string) $row->report_day,
                'orders_count' => (int) $row->orders_count,
                'amount' => (string) $row->amount,
            ];
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * Top-N по дням: отдельный SQL на каждый день периода с
     * `ORDER BY … LIMIT $limitPerDay` (MySQL 5.7 без window functions).
     * Период ограничен
     * ≤ {@see FoodReportFilterRequest::MAX_SPAN_DAYS} дней.
     */
    public function aggregateTopDishesByDay(ReportFilterDto $filter, int $limitPerDay): array
    {
        $dateExpression = $this->itemsDateExpression($filter->dateAxis);
        $result = [];

        $day = new DateTimeImmutable($filter->dateFrom);
        $end = new DateTimeImmutable($filter->dateTo);
        $oneDay = new DateInterval('P1D');

        while ($day <= $end) {
            $dayStr = $day->format('Y-m-d');

            $rows = FoodOrderItem::query()
                ->from('max_food_order_items as items')
                ->join('max_food_orders as orders', 'orders.id', '=', 'items.order_id')
                ->where('orders.status', OrderStatus::Confirmed)
                ->where('items.restaurant_id', $filter->restaurantId)
                ->whereRaw($dateExpression.' = ?', [$dayStr])
                ->selectRaw($dateExpression.' as report_day')
                ->selectRaw('items.dish_id as dish_id')
                ->selectRaw('items.dish_name as dish_name')
                ->selectRaw('SUM(items.quantity) as quantity')
                ->selectRaw('COALESCE(SUM(items.line_total), 0) as amount')
                ->groupBy(DB::raw($dateExpression), 'items.dish_id', 'items.dish_name')
                ->orderByDesc('quantity')
                ->orderByDesc('amount')
                ->orderBy('dish_name')
                ->limit($limitPerDay)
                ->get();

            foreach ($rows as $row) {
                $dishId = $row->dish_id;

                $result[] = [
                    'date' => $dayStr,
                    'dish_id' => $dishId !== null ? (int) $dishId : null,
                    'dish_name' => (string) $row->dish_name,
                    'quantity' => (int) $row->quantity,
                    'amount' => (string) $row->amount,
                ];
            }

            $day = $day->add($oneDay);
        }

        return $result;
    }

    /**
     * SQL-выражение оси даты для max_food_orders.
     */
    private function ordersDateExpression(ReportDateAxis $axis): string
    {
        return match ($axis) {
            ReportDateAxis::DeliveryDate => 'COALESCE(DATE(delivery_date), DATE(created_at))',
            ReportDateAxis::CreatedAt => 'DATE(created_at)',
        };
    }

    /**
     * SQL-выражение оси даты для items (+ join orders).
     */
    private function itemsDateExpression(ReportDateAxis $axis): string
    {
        return match ($axis) {
            ReportDateAxis::DeliveryDate => 'items.report_date',
            ReportDateAxis::CreatedAt => 'DATE(orders.created_at)',
        };
    }
}
