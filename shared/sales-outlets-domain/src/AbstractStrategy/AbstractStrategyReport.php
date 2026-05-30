<?php

namespace Shared\SalesOutletsDomain\AbstractStrategy;

use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

abstract class AbstractStrategyReport implements CsvReportStrategyInterface
{
    use ProvidesStrategyName;

    public function __construct(
        protected readonly CsvReportWriterInterface $csvWriter = new CsvReportWriter(),
    ) {}

    public function buildCsv(SalesOutletReportContextDto $context): string
    {
        $columns = $this->resolveColumns($context);

        return $this->csvWriter->writeFromColumns(
            $columns,
            $this->resolveRows($context, $columns),
        );
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    abstract protected function resolveColumns(SalesOutletReportContextDto $context): array;

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @return iterable<int, array<string, mixed>>
     */
    abstract protected function resolveRows(SalesOutletReportContextDto $context, array $columns): iterable;
}
