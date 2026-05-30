<?php

namespace Tests\Unit;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contracts\SalesOutlets\ReportMailSenderInterface;
use App\Contracts\SalesOutlets\SalesOutletsDownloadableReportStrategyInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\Reports\Html\ConfigMailReportConfigProvider;
use App\Services\SalesOutlets\Reports\Html\HtmlTableRenderer;
use App\Services\SalesOutlets\Reports\SalesOutletsReportContextFactory;
use App\Services\SalesOutlets\Reports\Strategies\HtmlEmailReportStrategy;
use App\Services\SalesOutlets\SalesOutletColumnSelector;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

class HtmlEmailReportStrategyTest extends TestCase
{
    public function test_build_html_uses_repository_rows(): void
    {
        $strategy = $this->makeStrategy(
            rows: [
                ['id' => '7', 'shop' => 'Самара'],
            ],
        );

        $filters = SalesOutletReportFilterDto::fromValidated(
            validated: ['columns' => ['id', 'shop']],
            allowedColumns: ['id', 'shop'],
        );

        $html = $strategy->build(
            (new SalesOutletsReportContextFactory)->fromReportFilter($filters),
        );

        $this->assertStringContainsString('Самара', $html);
        $this->assertStringContainsString('7', $html);
        $this->assertStringContainsString('<table', $html);
    }

    public function test_deliver_sends_mail_when_recipients_configured(): void
    {
        $mailSender = $this->createMock(ReportMailSenderInterface::class);
        $mailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                ['reports@example.test'],
                'Тестовый отчёт',
                '<table>html</table>',
            );

        $strategy = $this->makeStrategy(
            rows: [],
            mailSender: $mailSender,
            mailRecipients: ['reports@example.test'],
            mailSubject: 'Тестовый отчёт',
        );

        $delivery = $strategy->deliver($this->makeJob(), '<table>html</table>');

        $this->assertEquals(ReportDeliveryResult::none(), $delivery);
    }

    public function test_deliver_throws_when_recipients_are_empty(): void
    {
        $strategy = $this->makeStrategy(rows: [], mailRecipients: []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mail recipients are not configured.');

        $strategy->deliver($this->makeJob(), '<table></table>');
    }

    public function test_report_type_is_html_email(): void
    {
        $strategy = $this->makeStrategy(rows: []);

        $this->assertSame(SalesOutletsReportType::HtmlEmail, $strategy->reportType());
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
        ?ReportMailSenderInterface $mailSender = null,
        array $mailRecipients = ['reports@example.test'],
        string $mailSubject = 'Тестовый отчёт',
    ): HtmlEmailReportStrategy {
        $dataRepository = $this->createMock(SalesOutletsDataRepositoryInterface::class);
        $dataRepository
            ->method('reportRows')
            ->willReturn(new Collection($rows));

        $metadataRepository = $this->createMock(SalesOutletsMetadataRepositoryInterface::class);
        $metadataRepository->method('columns')->willReturn([
            ['key' => 'id', 'label' => 'ID объекта продаж'],
            ['key' => 'shop', 'label' => 'Магазин'],
        ]);

        return new HtmlEmailReportStrategy(
            dataRepository: $dataRepository,
            columnSelector: new SalesOutletColumnSelector($metadataRepository),
            htmlTableRenderer: new HtmlTableRenderer,
            mailSender: $mailSender ?? $this->createMock(ReportMailSenderInterface::class),
            mailReportConfig: new ConfigMailReportConfigProvider(
                $this->makeMailConfigRepository($mailRecipients, $mailSubject),
            ),
        );
    }

    /**
     * @param  array<int, string>  $mailRecipients
     */
    private function makeMailConfigRepository(
        array $mailRecipients,
        string $mailSubject,
    ): Repository {
        $config = $this->createMock(Repository::class);
        $config->method('get')->willReturnCallback(function (string $key, mixed $default = null) use ($mailRecipients, $mailSubject): mixed {
            return match ($key) {
                'sales_outlets_reports.types.html_email.recipients' => $mailRecipients,
                'sales_outlets_reports.types.html_email.subject' => $mailSubject,
                default => $default,
            };
        });

        return $config;
    }

    private function makeJob(): SalesOutletAsyncJob
    {
        return new SalesOutletAsyncJob(
            uuid: '11111111-1111-1111-1111-111111111111',
            userId: 10,
            status: AsyncJobStatus::Processing,
            reportType: SalesOutletsReportType::HtmlEmail,
            filters: SalesOutletReportFilterDto::fromValidated(
                validated: ['columns' => ['id', 'shop']],
                allowedColumns: ['id', 'shop'],
            ),
        );
    }
}
