<?php

namespace Shared\SalesOutletsDomain\AbstractStrategy;

use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

interface CsvReportStrategyInterface extends StrategyReportInterface
{
    public function buildCsv(SalesOutletReportContextDto $context): string;
}
