<?php

namespace App\Services\SalesOutlets\Reports;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Services\SalesOutlets\SalesOutletColumnSelector;
use Shared\SalesOutletsDomain\AbstractStrategy\AbstractStrategyReport;
use Shared\SalesOutletsDomain\AbstractStrategy\CsvReportWriterInterface;
use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

abstract class AbstractSalesOutletsCsvReportStrategy extends AbstractStrategyReport
{
    use ResolvesSalesOutletsReportData;

    public function __construct(
        SalesOutletsDataRepositoryInterface $dataRepository,
        SalesOutletColumnSelector $columnSelector,
        CsvReportWriterInterface $csvWriter,
    ) {
        $this->dataRepository = $dataRepository;
        $this->columnSelector = $columnSelector;

        parent::__construct($csvWriter);
    }

    public function build(SalesOutletReportContextDto $context): string
    {
        return $this->buildCsv($context);
    }
}
