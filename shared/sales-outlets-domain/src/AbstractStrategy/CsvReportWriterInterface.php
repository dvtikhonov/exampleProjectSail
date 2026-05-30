<?php

namespace Shared\SalesOutletsDomain\AbstractStrategy;

interface CsvReportWriterInterface
{
    /**
     * @param  array<int, string>  $headerLabels
     * @param  iterable<int, array<int, string>>  $rows
     */
    public function write(array $headerLabels, iterable $rows): string;

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public function writeFromColumns(array $columns, iterable $rows): string;
}
