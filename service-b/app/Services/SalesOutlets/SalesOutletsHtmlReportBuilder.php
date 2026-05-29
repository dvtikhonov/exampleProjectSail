<?php

namespace App\Services\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use Illuminate\Support\Collection;

class SalesOutletsHtmlReportBuilder
{
    public function __construct(
        private readonly SalesOutletColumnSelector $columnSelector,
    ) {}

    /**
     * @param  Collection<int, array<string, int|string|null>>  $rows
     */
    public function build(SalesOutletExportFilterDto $filters, Collection $rows): string
    {
        $columns = $this->columnSelector->select($filters);

        $headerCells = array_map(
            fn (array $column): string => '<th>'.e($column['label']).'</th>',
            $columns,
        );

        $bodyRows = [];

        foreach ($rows as $row) {
            $cells = array_map(
                fn (array $column): string => '<td>'.e((string) ($row[$column['key']] ?? '')).'</td>',
                $columns,
            );

            $bodyRows[] = '<tr>'.implode('', $cells).'</tr>';
        }

        return '<table border="1" cellpadding="6" cellspacing="0">'
            .'<thead><tr>'.implode('', $headerCells).'</tr></thead>'
            .'<tbody>'.implode('', $bodyRows).'</tbody>'
            .'</table>';
    }
}
