<?php

namespace Tests\Unit;

use App\Contracts\Max\MaxReportConfigProviderInterface;
use App\Contracts\Max\ReportMaxMessageSenderInterface;
use App\DTO\Max\MaxReportConfig;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsDownloadableReportStrategyInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\Reports\SalesOutletsReportContextFactory;
use App\Services\SalesOutlets\Reports\Strategies\MaxMessageReportStrategy;
use App\Services\SalesOutlets\SalesOutletColumnSelector;
use Illuminate\Support\Collection;
use Shared\SalesOutletsDomain\AbstractStrategy\CsvReportWriter;
use Tests\TestCase;

class MaxMessageReportStrategyTest extends TestCase
{
    public function test_build_returns_csv_with_selected_columns(): void
    {
        $strategy = $this->makeStrategy(
            rows: [
                ['id' => '5', 'shop' => 'Тула'],
            ],
        );

        $filters = SalesOutletReportFilterDto::fromValidated(
            validated: ['columns' => ['id', 'shop']],
            allowedColumns: ['id', 'shop'],
        );

        $csv = $strategy->build(
            (new SalesOutletsReportContextFactory)->fromReportFilter($filters),
        );

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"ID объекта продаж";"Магазин"', $csv);
        $this->assertStringContainsString('"5";"Тула"', $csv);
        $this->assertStringNotContainsString(' | ', $csv);
    }

    public function test_deliver_sends_intro_and_csv_via_max_sender(): void
    {
        $maxSender = $this->createMock(ReportMaxMessageSenderInterface::class);
        $maxSender
            ->expects($this->once())
            ->method('send')
            ->with(
                'Объекты продаж — отчёт',
                "\xEF\xBB\xBF\"ID\";\"Shop\"\n\"1\";\"A\"",
                'objects-sales-outlets-10.csv',
            );

        $strategy = $this->makeStrategy(
            rows: [],
            maxSender: $maxSender,
            intro: 'Объекты продаж — отчёт',
        );

        $delivery = $strategy->deliver(
            $this->makeJob(),
            "\xEF\xBB\xBF\"ID\";\"Shop\"\n\"1\";\"A\"",
        );

        $this->assertEquals(ReportDeliveryResult::none(), $delivery);
    }

    public function test_report_type_is_max_message(): void
    {
        $strategy = $this->makeStrategy(rows: []);

        $this->assertSame(SalesOutletsReportType::MaxMessage, $strategy->reportType());
    }

    public function test_does_not_support_download(): void
    {
        $strategy = $this->makeStrategy(rows: []);

        $this->assertNotInstanceOf(SalesOutletsDownloadableReportStrategyInterface::class, $strategy);
    }

    /**
     * @param  iterable<int, array<string, int|string|null>>  $rows
     */
    private function makeStrategy(
        iterable $rows,
        ?ReportMaxMessageSenderInterface $maxSender = null,
        string $intro = '',
    ): MaxMessageReportStrategy {
        $dataRepository = $this->createMock(SalesOutletsDataRepositoryInterface::class);
        $dataRepository
            ->method('reportRows')
            ->willReturn(new Collection($rows));

        $metadataRepository = $this->createMock(SalesOutletsMetadataRepositoryInterface::class);
        $metadataRepository->method('columns')->willReturn([
            ['key' => 'id', 'label' => 'ID объекта продаж'],
            ['key' => 'shop', 'label' => 'Магазин'],
        ]);

        return new MaxMessageReportStrategy(
            dataRepository: $dataRepository,
            columnSelector: new SalesOutletColumnSelector($metadataRepository),
            csvWriter: new CsvReportWriter,
            maxMessageSender: $maxSender ?? $this->createMock(ReportMaxMessageSenderInterface::class),
            maxReportConfig: $this->makeConfigProvider($intro),
        );
    }

    private function makeConfigProvider(string $intro): MaxReportConfigProviderInterface
    {
        $provider = $this->createMock(MaxReportConfigProviderInterface::class);
        $provider->method('config')->willReturn(new MaxReportConfig(
            chatIds: [12345],
            userIds: [],
            intro: $intro,
            maxTextLength: 4000,
            rateLimitRetryMax: 2,
            rateLimitRetryDelayMs: 500,
            interRecipientDelayMs: 50,
        ));

        return $provider;
    }

    private function makeJob(): SalesOutletAsyncJob
    {
        return new SalesOutletAsyncJob(
            uuid: '66666666-6666-6666-6666-666666666666',
            userId: 10,
            status: AsyncJobStatus::Processing,
            reportType: SalesOutletsReportType::MaxMessage,
            filters: SalesOutletReportFilterDto::fromValidated(
                validated: ['columns' => ['id', 'shop']],
                allowedColumns: ['id', 'shop'],
            ),
        );
    }
}
