<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\SalesOutletsDownloadableReportStrategyInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadCapabilityInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadPresentationInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessingStrategyInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStrategyResolverInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\Reports\SalesOutletsReportStrategyRegistry;
use App\Services\SalesOutlets\Reports\Strategies\CsvDownloadReportStrategy;
use App\Services\SalesOutlets\Reports\Strategies\HtmlEmailReportStrategy;
use App\Services\SalesOutlets\Reports\Strategies\MaxMessageReportStrategy;
use InvalidArgumentException;
use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;
use Tests\TestCase;

class SalesOutletsReportStrategyRegistryTest extends TestCase
{
    public function test_it_resolves_csv_download_strategy(): void
    {
        $registry = new SalesOutletsReportStrategyRegistry([
            $this->createStubStrategy(SalesOutletsReportType::CsvDownload),
            $this->createStubStrategy(SalesOutletsReportType::HtmlEmail),
        ]);

        $strategy = $registry->resolve(SalesOutletsReportType::CsvDownload);

        $this->assertSame(SalesOutletsReportType::CsvDownload, $strategy->reportType());
    }

    public function test_it_resolves_html_email_strategy(): void
    {
        $registry = new SalesOutletsReportStrategyRegistry([
            $this->createStubStrategy(SalesOutletsReportType::HtmlEmail),
            $this->createStubStrategy(SalesOutletsReportType::CsvDownload),
        ]);

        $strategy = $registry->resolve(SalesOutletsReportType::HtmlEmail);

        $this->assertSame(SalesOutletsReportType::HtmlEmail, $strategy->reportType());
    }

    public function test_it_throws_for_unknown_report_type(): void
    {
        $registry = new SalesOutletsReportStrategyRegistry([
            $this->createStubStrategy(SalesOutletsReportType::CsvDownload),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No report strategy registered for type [html_email].');

        $registry->resolve(SalesOutletsReportType::HtmlEmail);
    }

    public function test_container_registers_tagged_strategies(): void
    {
        $strategies = iterator_to_array($this->app->tagged('sales-outlets.report-strategies'));

        $this->assertCount(3, $strategies);
        $this->assertContainsOnlyInstancesOf(SalesOutletsReportProcessingStrategyInterface::class, $strategies);

        $types = array_map(
            fn (SalesOutletsReportProcessingStrategyInterface $strategy): string => $strategy->reportType()->value,
            $strategies,
        );

        $this->assertEqualsCanonicalizing(
            [
                SalesOutletsReportType::CsvDownload->value,
                SalesOutletsReportType::HtmlEmail->value,
                SalesOutletsReportType::MaxMessage->value,
            ],
            $types,
        );
    }

    public function test_container_resolves_registry_with_both_strategies(): void
    {
        $registry = $this->app->make(SalesOutletsReportStrategyRegistry::class);

        $this->assertInstanceOf(CsvDownloadReportStrategy::class, $registry->resolve(SalesOutletsReportType::CsvDownload));
        $this->assertInstanceOf(HtmlEmailReportStrategy::class, $registry->resolve(SalesOutletsReportType::HtmlEmail));
        $this->assertInstanceOf(MaxMessageReportStrategy::class, $registry->resolve(SalesOutletsReportType::MaxMessage));
    }

    public function test_supports_download_returns_true_for_csv(): void
    {
        $registry = new SalesOutletsReportStrategyRegistry([
            $this->createStubStrategy(SalesOutletsReportType::CsvDownload),
            $this->createStubStrategy(SalesOutletsReportType::HtmlEmail),
        ]);

        $this->assertTrue($registry->supportsDownload(SalesOutletsReportType::CsvDownload));
    }

    public function test_supports_download_returns_false_for_html_email(): void
    {
        $registry = new SalesOutletsReportStrategyRegistry([
            $this->createStubStrategy(SalesOutletsReportType::CsvDownload),
            $this->createStubStrategy(SalesOutletsReportType::HtmlEmail),
        ]);

        $this->assertFalse($registry->supportsDownload(SalesOutletsReportType::HtmlEmail));
    }

    public function test_container_supports_download_for_real_strategies(): void
    {
        $registry = $this->app->make(SalesOutletsReportStrategyRegistry::class);

        $this->assertTrue($registry->supportsDownload(SalesOutletsReportType::CsvDownload));
        $this->assertFalse($registry->supportsDownload(SalesOutletsReportType::HtmlEmail));
        $this->assertFalse($registry->supportsDownload(SalesOutletsReportType::MaxMessage));
    }

    public function test_container_aliases_resolver_and_download_capability_to_same_registry(): void
    {
        $registry = $this->app->make(SalesOutletsReportStrategyRegistry::class);
        $resolver = $this->app->make(SalesOutletsReportStrategyResolverInterface::class);
        $downloadCapability = $this->app->make(SalesOutletsReportDownloadCapabilityInterface::class);
        $presentation = $this->app->make(SalesOutletsReportDownloadPresentationInterface::class);

        $this->assertSame($registry, $resolver);
        $this->assertSame($registry, $downloadCapability);
        $this->assertSame($registry, $presentation);
    }

    public function test_download_presentation_delegates_to_csv_strategy(): void
    {
        $registry = $this->app->make(SalesOutletsReportDownloadPresentationInterface::class);

        $job = new SalesOutletAsyncJob(
            uuid: '11111111-1111-1111-1111-111111111111',
            userId: 42,
            status: AsyncJobStatus::Completed,
            reportType: SalesOutletsReportType::CsvDownload,
            filters: SalesOutletReportFilterDto::fromValidated(
                validated: ['columns' => ['id']],
                allowedColumns: ['id'],
            ),
        );

        $this->assertSame('objects-sales-outlets-42.csv', $registry->downloadFileName($job));
        $this->assertSame('text/csv; charset=UTF-8', $registry->downloadContentType(SalesOutletsReportType::CsvDownload));
    }

    public function test_download_presentation_throws_for_non_downloadable_type(): void
    {
        $registry = new SalesOutletsReportStrategyRegistry([
            $this->createStubStrategy(SalesOutletsReportType::HtmlEmail),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Report type [html_email] does not support download presentation.');

        $registry->downloadContentType(SalesOutletsReportType::HtmlEmail);
    }

    private function createStubStrategy(SalesOutletsReportType $type): SalesOutletsReportProcessingStrategyInterface
    {
        if ($type === SalesOutletsReportType::CsvDownload) {
            return new class($type) implements SalesOutletsDownloadableReportStrategyInterface
            {
                public function __construct(
                    private readonly SalesOutletsReportType $type,
                ) {}

                public function reportType(): SalesOutletsReportType
                {
                    return $this->type;
                }

                public function build(SalesOutletReportContextDto $context): string
                {
                    return '';
                }

                public function deliver(SalesOutletAsyncJob $job, string $content): ReportDeliveryResult
                {
                    return ReportDeliveryResult::none();
                }

                public function storagePathForJob(SalesOutletAsyncJob $job): string
                {
                    return 'reports/stub.csv';
                }

                public function downloadFileName(SalesOutletAsyncJob $job): string
                {
                    return 'stub.csv';
                }

                public function downloadContentType(): string
                {
                    return 'text/csv; charset=UTF-8';
                }
            };
        }

        return new class($type) implements SalesOutletsReportProcessingStrategyInterface
        {
            public function __construct(
                private readonly SalesOutletsReportType $type,
            ) {}

            public function reportType(): SalesOutletsReportType
            {
                return $this->type;
            }

            public function build(SalesOutletReportContextDto $context): string
            {
                return '';
            }

            public function deliver(SalesOutletAsyncJob $job, string $content): ReportDeliveryResult
            {
                return ReportDeliveryResult::none();
            }
        };
    }
}
