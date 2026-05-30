<?php

namespace Shared\SalesOutletsDomain\AbstractStrategy;

use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

interface HtmlReportStrategyInterface extends StrategyReportInterface
{
    public function buildHtml(SalesOutletReportContextDto $context): string;
}
