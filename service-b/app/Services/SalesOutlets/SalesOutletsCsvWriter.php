<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\SalesOutletsCsvWriterInterface;
use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;

class SalesOutletsCsvWriter implements SalesOutletsCsvWriterInterface
{
    public function __construct(
        private readonly SalesOutletsDataRepositoryInterface $dataRepository,
        private readonly SalesOutletColumnSelector $columnSelector,
    ) {}

    public function build(SalesOutletExportFilterDto $filters): string
    {
        $columns = $this->columnSelector->select($filters);

        $rows = [$this->csvLine(array_column($columns, 'label'))];

        foreach ($this->dataRepository->exportRows($filters) as $row) {
            $rows[] = $this->csvLine(array_map(
                fn (array $column): string => (string) ($row[$column['key']] ?? ''),
                $columns,
            ));
        }

        return "\xEF\xBB\xBF".implode("\n", $rows);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function csvLine(array $values): string
    {
        return implode(';', array_map(
            fn (string $value): string => '"'.str_replace('"', '""', $value).'"',
            $values,
        ));
    }
}
