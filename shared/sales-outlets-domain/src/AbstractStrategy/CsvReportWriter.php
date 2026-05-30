<?php

namespace Shared\SalesOutletsDomain\AbstractStrategy;

final class CsvReportWriter implements CsvReportWriterInterface
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    private const DELIMITER = ';';

    /**
     * @param  array<int, string>  $headerLabels
     * @param  iterable<int, array<int, string>>  $rows
     */
    public function write(array $headerLabels, iterable $rows): string
    {
        $lines = [$this->csvLine($headerLabels)];

        foreach ($rows as $row) {
            $lines[] = $this->csvLine($row);
        }

        return self::UTF8_BOM.implode("\n", $lines);
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public function writeFromColumns(array $columns, iterable $rows): string
    {
        $headerLabels = array_column($columns, 'label');
        $lines = [$this->csvLine($headerLabels)];

        foreach ($rows as $row) {
            $lines[] = $this->csvLine(array_map(
                fn (array $column): string => (string) ($row[$column['key']] ?? ''),
                $columns,
            ));
        }

        return self::UTF8_BOM.implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function csvLine(array $values): string
    {
        return implode(self::DELIMITER, array_map(
            fn (string $value): string => '"'.str_replace('"', '""', $value).'"',
            $values,
        ));
    }
}
