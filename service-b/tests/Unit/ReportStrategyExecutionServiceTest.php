<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\SalesOutletsReportContextFactoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessingStrategyInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStrategyResolverInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\ReportStrategyExecutionService;
use PHPUnit\Framework\TestCase;
use Shared\SalesOutletsDomain\DTO\SalesOutletFilterDto;
use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

class ReportStrategyExecutionServiceTest extends TestCase
{
    public function test_execute_resolves_strategy_builds_content_and_delivers(): void
    {
        $job = $this->makeJob();
        $context = SalesOutletReportContextDto::fromFilterValues(
            filters: $this->makeFilterDto(),
            columnKeys: ['id'],
        );
        $content = '<table></table>';
        $delivery = ReportDeliveryResult::none();

        $strategy = $this->createMock(SalesOutletsReportProcessingStrategyInterface::class);
        $strategy
            ->expects($this->once())
            ->method('build')
            ->with($context)
            ->willReturn($content);
        $strategy
            ->expects($this->once())
            ->method('deliver')
            ->with($job, $content)
            ->willReturn($delivery);

        $strategyResolver = $this->createMock(SalesOutletsReportStrategyResolverInterface::class);
        $strategyResolver
            ->expects($this->once())
            ->method('resolve')
            ->with(SalesOutletsReportType::HtmlEmail)
            ->willReturn($strategy);

        $contextFactory = $this->createMock(SalesOutletsReportContextFactoryInterface::class);
        $contextFactory
            ->expects($this->once())
            ->method('fromJob')
            ->with($job)
            ->willReturn($context);

        $service = new ReportStrategyExecutionService(
            strategyResolver: $strategyResolver,
            contextFactory: $contextFactory,
        );

        $result = $service->execute($job);

        $this->assertSame($delivery, $result);
    }

    public function test_execute_returns_file_delivery_from_strategy(): void
    {
        $job = $this->makeJob(SalesOutletsReportType::CsvDownload);
        $context = SalesOutletReportContextDto::fromFilterValues(
            filters: $this->makeFilterDto(),
            columnKeys: ['id', 'shop'],
        );
        $delivery = ReportDeliveryResult::withFile('reports/job.csv');

        $strategy = $this->createMock(SalesOutletsReportProcessingStrategyInterface::class);
        $strategy->method('build')->willReturn('csv-content');
        $strategy->method('deliver')->willReturn($delivery);

        $strategyResolver = $this->createMock(SalesOutletsReportStrategyResolverInterface::class);
        $strategyResolver->method('resolve')->willReturn($strategy);

        $contextFactory = $this->createMock(SalesOutletsReportContextFactoryInterface::class);
        $contextFactory->method('fromJob')->willReturn($context);

        $service = new ReportStrategyExecutionService(
            strategyResolver: $strategyResolver,
            contextFactory: $contextFactory,
        );

        $result = $service->execute($job);

        $this->assertSame('reports/job.csv', $result->filePath);
    }

    private function makeFilterDto(): SalesOutletFilterDto
    {
        return new SalesOutletFilterDto(
            search: '',
            status: '',
            columnFilters: [],
            sort: 'id',
            direction: 'asc',
        );
    }

    private function makeJob(
        SalesOutletsReportType $reportType = SalesOutletsReportType::HtmlEmail,
    ): SalesOutletAsyncJob {
        return new SalesOutletAsyncJob(
            uuid: '33333333-3333-3333-3333-333333333333',
            userId: 7,
            status: AsyncJobStatus::Processing,
            reportType: $reportType,
            filters: SalesOutletReportFilterDto::fromValidated(
                validated: ['columns' => ['id', 'shop']],
                allowedColumns: ['id', 'shop'],
            ),
        );
    }
}
