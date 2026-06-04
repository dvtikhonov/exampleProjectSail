<?php

namespace App\Services\SalesOutlets\Reports\Strategies;

use App\Contracts\Max\MaxReportConfigProviderInterface;
use App\Contracts\Max\ReportMaxMessageSenderInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessingStrategyInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\Reports\AbstractSalesOutletsCsvReportStrategy;
use App\Services\SalesOutlets\SalesOutletColumnSelector;
use Shared\SalesOutletsDomain\AbstractStrategy\CsvReportWriterInterface;

class MaxMessageReportStrategy extends AbstractSalesOutletsCsvReportStrategy implements SalesOutletsReportProcessingStrategyInterface
{
    public function __construct(
        SalesOutletsDataRepositoryInterface $dataRepository,
        SalesOutletColumnSelector $columnSelector,
        CsvReportWriterInterface $csvWriter,
        private readonly ReportMaxMessageSenderInterface $maxMessageSender,
        private readonly MaxReportConfigProviderInterface $maxReportConfig,
    ) {
        parent::__construct($dataRepository, $columnSelector, $csvWriter);
    }

    public function reportType(): SalesOutletsReportType
    {
        return SalesOutletsReportType::MaxMessage;
    }

    public function deliver(SalesOutletAsyncJob $job, string $content): ReportDeliveryResult
    {
        $config = $this->maxReportConfig->config();

        $this->maxMessageSender->send(
            text: trim($config->intro),
            csvContent: $content,
            fileName: $this->csvFileName($job),
        );

        return ReportDeliveryResult::none();
    }

    private function csvFileName(SalesOutletAsyncJob $job): string
    {
        if ($job->userId === null) {
            return 'objects-sales-outlets.csv';
        }

        return 'objects-sales-outlets-'.$job->userId.'.csv';
    }
}
