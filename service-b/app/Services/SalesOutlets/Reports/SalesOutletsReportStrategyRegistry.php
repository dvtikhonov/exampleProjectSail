<?php

namespace App\Services\SalesOutlets\Reports;

use App\Contracts\SalesOutlets\SalesOutletsDownloadableReportStrategyInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadCapabilityInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadPresentationInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessingStrategyInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStrategyResolverInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\Enums\SalesOutletsReportType;
use InvalidArgumentException;

/**
 * Implements resolver, download-capability and presentation interfaces (ISP segregation).
 * Registered in AppServiceProvider as one singleton with multiple interface aliases.
 */
class SalesOutletsReportStrategyRegistry implements
    SalesOutletsReportStrategyResolverInterface,
    SalesOutletsReportDownloadCapabilityInterface,
    SalesOutletsReportDownloadPresentationInterface
{
    /**
     * @param  iterable<SalesOutletsReportProcessingStrategyInterface>  $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
    ) {}

    public function resolve(SalesOutletsReportType $type): SalesOutletsReportProcessingStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->reportType() === $type) {
                return $strategy;
            }
        }

        throw new InvalidArgumentException(
            sprintf('No report strategy registered for type [%s].', $type->value),
        );
    }

    public function supportsDownload(SalesOutletsReportType $type): bool
    {
        return $this->resolve($type) instanceof SalesOutletsDownloadableReportStrategyInterface;
    }

    public function downloadFileName(SalesOutletAsyncJob $job): string
    {
        return $this->resolveDownloadable($job->reportType)->downloadFileName($job);
    }

    public function downloadContentType(SalesOutletsReportType $type): string
    {
        return $this->resolveDownloadable($type)->downloadContentType();
    }

    private function resolveDownloadable(SalesOutletsReportType $type): SalesOutletsDownloadableReportStrategyInterface
    {
        $strategy = $this->resolve($type);

        if (! $strategy instanceof SalesOutletsDownloadableReportStrategyInterface) {
            throw new InvalidArgumentException(
                sprintf('Report type [%s] does not support download presentation.', $type->value),
            );
        }

        return $strategy;
    }
}
