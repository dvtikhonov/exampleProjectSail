<?php

namespace App\Services\Max;

/**
 * Таблица в виде текста для MAX: API поддерживает только базовый HTML/Markdown
 * (без &lt;table&gt;), иначе теги удаляются и ячейки слипаются в одну строку.
 */
class PlainTextTableRenderer
{
    private const COLUMN_SEPARATOR = ' | ';

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  iterable<int, array<string, int|string|null>>  $rows
     */
    public function render(array $columns, iterable $rows): string
    {
        if ($columns === []) {
            return '';
        }

        $rowsArray = $rows instanceof \Traversable
            ? iterator_to_array($rows, preserve_keys: false)
            : array_values($rows);

        $widths = $this->columnWidths($columns, $rowsArray);
        $lines = [
            $this->formatRow($columns, $widths, static fn (array $column): string => $column['label']),
        ];

        foreach ($rowsArray as $row) {
            $lines[] = $this->formatRow(
                $columns,
                $widths,
                static fn (array $column): string => (string) ($row[$column['key']] ?? ''),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<int, array<string, int|string|null>>  $rowsArray
     * @return array<string, int>
     */
    private function columnWidths(array $columns, array $rowsArray): array
    {
        $widths = [];

        foreach ($columns as $column) {
            $widths[$column['key']] = mb_strlen($column['label']);
        }

        foreach ($rowsArray as $row) {
            foreach ($columns as $column) {
                $value = $this->sanitizeCell((string) ($row[$column['key']] ?? ''));
                $widths[$column['key']] = max(
                    $widths[$column['key']],
                    mb_strlen($value),
                );
            }
        }

        return $widths;
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<string, int>  $widths
     * @param  callable(array{key: string, label: string}): string  $valueResolver
     */
    private function formatRow(array $columns, array $widths, callable $valueResolver): string
    {
        $cells = [];

        foreach ($columns as $column) {
            $value = $this->sanitizeCell($valueResolver($column));
            $cells[] = $this->padCell($value, $widths[$column['key']]);
        }

        return implode(self::COLUMN_SEPARATOR, $cells);
    }

    private function sanitizeCell(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        return str_replace('|', '¦', $value);
    }

    private function padCell(string $value, int $width): string
    {
        $length = mb_strlen($value);

        if ($length >= $width) {
            return $value;
        }

        return $value.str_repeat(' ', $width - $length);
    }
}
