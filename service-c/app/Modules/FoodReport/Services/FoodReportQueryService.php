<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Services;

use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\Modules\FoodReport\Contracts\FoodOrderReportRepositoryInterface;
use App\Modules\FoodReport\Contracts\FoodReportQueryServiceInterface;
use App\Modules\FoodReport\DTO\ReportFilterDto;
use App\Modules\FoodReport\DTO\RevenueDayRowDto;
use App\Modules\FoodReport\DTO\RevenueReportDto;
use App\Modules\FoodReport\DTO\TopDishDayRowDto;
use App\Modules\FoodReport\DTO\TopDishesReportDto;
use App\Modules\FoodReport\FoodReportLimits;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

/**
 * Query-сервис отчётов Food: выручка (items_total) и топ блюд (только confirmed).
 */
final class FoodReportQueryService implements FoodReportQueryServiceInterface
{
    public function __construct(
        private readonly FoodOrderReportRepositoryInterface $reportRepository,
        private readonly FoodMoneyFormatterInterface $moneyFormatter,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function revenue(ReportFilterDto $filter): RevenueReportDto
    {
        $this->assertSpanWithinLimit($filter);

        $rawDays = $this->reportRepository->aggregateRevenueByDay($filter);
        usort(
            $rawDays,
            static fn (array $left, array $right): int => strcmp((string) $left['date'], (string) $right['date']),
        );

        $days = [];
        $metaOrdersCount = 0;
        $metaAmount = 0.0;

        foreach ($rawDays as $row) {
            $ordersCount = (int) $row['orders_count'];
            $amount = (float) $row['amount'];
            $average = $ordersCount > 0 ? $amount / $ordersCount : 0.0;

            $days[] = new RevenueDayRowDto(
                date: (string) $row['date'],
                ordersCount: $ordersCount,
                averageCheck: $this->moneyFormatter->format($average),
                amount: $this->moneyFormatter->format($amount),
            );

            $metaOrdersCount += $ordersCount;
            $metaAmount += $amount;
        }

        $metaAverage = $metaOrdersCount > 0 ? $metaAmount / $metaOrdersCount : 0.0;

        return new RevenueReportDto(
            days: $days,
            metaOrdersCount: $metaOrdersCount,
            metaAverageCheck: $this->moneyFormatter->format($metaAverage),
            metaAmount: $this->moneyFormatter->format($metaAmount),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function topDishes(ReportFilterDto $filter, int $limitPerDay = 20): TopDishesReportDto
    {
        $this->assertSpanWithinLimit($filter);

        $rawRows = $this->reportRepository->aggregateTopDishesByDay($filter, $limitPerDay);

        $rows = [];

        foreach ($rawRows as $row) {
            $dishId = $row['dish_id'] ?? null;

            $rows[] = new TopDishDayRowDto(
                date: (string) $row['date'],
                dishId: $dishId !== null ? (int) $dishId : null,
                dishName: (string) $row['dish_name'],
                quantity: (int) $row['quantity'],
                amount: $this->moneyFormatter->format((float) $row['amount']),
            );
        }

        return new TopDishesReportDto($rows);
    }

    /**
     * Защита сервисного слоя: период не длиннее {@see FoodReportLimits::MAX_SPAN_DAYS}.
     * Логика совпадает с UX-проверкой в FoodReportFilterRequest.
     *
     * @throws InvalidArgumentException
     */
    private function assertSpanWithinLimit(ReportFilterDto $filter): void
    {
        try {
            $fromDate = new DateTimeImmutable($filter->dateFrom);
            $toDate = new DateTimeImmutable($filter->dateTo);
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                'Некорректный период отчёта: date_from/date_to должны быть датами Y-m-d.',
                0,
                $exception,
            );
        }

        $spanDays = (int) $fromDate->diff($toDate)->days;

        if ($spanDays > FoodReportLimits::MAX_SPAN_DAYS) {
            throw new InvalidArgumentException(
                'Период отчёта не может превышать '.FoodReportLimits::MAX_SPAN_DAYS.' дня.',
            );
        }
    }
}
