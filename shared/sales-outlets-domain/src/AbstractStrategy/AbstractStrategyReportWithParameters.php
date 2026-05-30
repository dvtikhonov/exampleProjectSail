<?php

namespace Shared\SalesOutletsDomain\AbstractStrategy;

use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

abstract class AbstractStrategyReportWithParameters extends AbstractStrategyReport implements AbstractStrategyReportWithParametersInterface
{
    /**
     * @return array<string, mixed>
     */
    protected function params(SalesOutletReportContextDto $context): array
    {
        return $context->params;
    }
}
