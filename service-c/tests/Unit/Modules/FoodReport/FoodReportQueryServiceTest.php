<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FoodReport;

use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\Modules\FoodReport\Contracts\FoodOrderReportRepositoryInterface;
use App\Modules\FoodReport\DTO\ReportFilterDto;
use App\Modules\FoodReport\Enums\ReportDateAxis;
use App\Modules\FoodReport\FoodReportLimits;
use App\Modules\FoodReport\Services\FoodReportQueryService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit: FoodReportQueryService — assertSpanWithinLimit для revenue/topDishes.
 */
final class FoodReportQueryServiceTest extends TestCase
{
    /** revenue отклоняет период длиннее MAX_SPAN_DAYS до обращения к репозиторию. */
    public function test_revenue_rejects_span_over_max_days(): void
    {
        $repository = $this->createMock(FoodOrderReportRepositoryInterface::class);
        $repository->expects($this->never())->method('aggregateRevenueByDay');

        $service = new FoodReportQueryService($repository, $this->moneyFormatter());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Период отчёта не может превышать '.FoodReportLimits::MAX_SPAN_DAYS.' дня.',
        );

        $service->revenue($this->filter(
            dateFrom: '2026-01-01',
            dateTo: '2026-04-05',
        ));
    }

    /** topDishes отклоняет период длиннее MAX_SPAN_DAYS до обращения к репозиторию. */
    public function test_top_dishes_rejects_span_over_max_days(): void
    {
        $repository = $this->createMock(FoodOrderReportRepositoryInterface::class);
        $repository->expects($this->never())->method('aggregateTopDishesByDay');

        $service = new FoodReportQueryService($repository, $this->moneyFormatter());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Период отчёта не может превышать '.FoodReportLimits::MAX_SPAN_DAYS.' дня.',
        );

        $service->topDishes($this->filter(
            dateFrom: '2026-01-01',
            dateTo: '2026-04-05',
        ));
    }

    /** Граница MAX_SPAN_DAYS (diff == лимит) допускается, репозиторий вызывается. */
    public function test_revenue_allows_span_equal_to_max_days(): void
    {
        $filter = $this->filter(
            dateFrom: '2026-01-01',
            dateTo: '2026-04-04',
        );

        $repository = $this->createMock(FoodOrderReportRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('aggregateRevenueByDay')
            ->with($filter)
            ->willReturn([]);

        $service = new FoodReportQueryService($repository, $this->moneyFormatter());
        $report = $service->revenue($filter);

        $this->assertSame([], $report->days);
        $this->assertSame(0, $report->metaOrdersCount);
    }

    private function moneyFormatter(): FoodMoneyFormatterInterface
    {
        $formatter = $this->createMock(FoodMoneyFormatterInterface::class);
        $formatter->method('format')->willReturnCallback(
            static fn (string|float|int $amount): string => number_format((float) $amount, 2, '.', ''),
        );

        return $formatter;
    }

    private function filter(string $dateFrom, string $dateTo): ReportFilterDto
    {
        return new ReportFilterDto(
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            restaurantId: 1,
            dateAxis: ReportDateAxis::DeliveryDate,
        );
    }
}
