<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ReportStrategyExecutionInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportContextFactoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStrategyResolverInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;

class ReportStrategyExecutionService implements ReportStrategyExecutionInterface
{
    public function __construct(
        private readonly SalesOutletsReportStrategyResolverInterface $strategyResolver,
        private readonly SalesOutletsReportContextFactoryInterface $contextFactory,
    ) {}

    public function execute(SalesOutletAsyncJob $job): ReportDeliveryResult
    {
        $strategy = $this->strategyResolver->resolve($job->reportType);
        $context = $this->contextFactory->fromJob($job);
        $content = $strategy->build($context);

        return $strategy->deliver($job, $content);
    }
}
