<?php

namespace App\Services\SalesOutlets\Reports\Html;

use App\Contracts\SalesOutlets\HtmlTableRendererInterface;

class HtmlTableRenderer implements HtmlTableRendererInterface
{
    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  iterable<int, array<string, int|string|null>>  $rows
     */
    public function render(array $columns, iterable $rows): string
    {
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
