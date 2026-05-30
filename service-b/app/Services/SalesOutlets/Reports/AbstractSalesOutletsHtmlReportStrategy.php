<?php

namespace App\Services\SalesOutlets\Reports;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessingStrategyInterface;
use App\Services\SalesOutlets\SalesOutletColumnSelector;
use Shared\SalesOutletsDomain\AbstractStrategy\HtmlReportStrategyInterface;
use Shared\SalesOutletsDomain\AbstractStrategy\ProvidesStrategyName;
use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

abstract class AbstractSalesOutletsHtmlReportStrategy implements HtmlReportStrategyInterface, SalesOutletsReportProcessingStrategyInterface
{
    use ProvidesStrategyName;
    use ResolvesSalesOutletsReportData;

    public function __construct(
        SalesOutletsDataRepositoryInterface $dataRepository,
        SalesOutletColumnSelector $columnSelector,
    ) {
        $this->dataRepository = $dataRepository;
        $this->columnSelector = $columnSelector;
    }

    public function build(SalesOutletReportContextDto $context): string
    {
        return $this->buildHtml($context);
    }
}
