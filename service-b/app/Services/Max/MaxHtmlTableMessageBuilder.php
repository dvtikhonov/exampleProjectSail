<?php

namespace App\Services\Max;

use App\DTO\Max\MaxMessageBuildResult;
use Traversable;

class MaxHtmlTableMessageBuilder
{
    private const FOOTER_TEMPLATE = 'Показаны первые %d из %d. Полный отчёт — CSV-экспорт на портале.';

    public function __construct(
        private readonly PlainTextTableRenderer $tableRenderer,
    ) {}

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  iterable<int, array<string, int|string|null>>  $rows
     */
    public function build(
        string $intro,
        array $columns,
        iterable $rows,
        int $maxTextLength = 4000,
    ): MaxMessageBuildResult {
        $rowsArray = $this->normalizeRows($rows);
        $totalRows = count($rowsArray);
        $introSection = $this->formatIntroSection($intro);

        if ($totalRows === 0) {
            return new MaxMessageBuildResult(
                text: $this->ensureWithinLimit(trim($intro), $maxTextLength),
                totalRows: 0,
                includedRows: 0,
                truncated: false,
            );
        }

        $fullTable = $this->tableRenderer->render($columns, $rowsArray);
        $fullText = $introSection.$fullTable;

        if (mb_strlen($fullText) <= $maxTextLength) {
            return new MaxMessageBuildResult(
                text: $fullText,
                totalRows: $totalRows,
                includedRows: $totalRows,
                truncated: false,
            );
        }

        $includedRows = 0;
        $text = $introSection;

        for ($rowCount = 1; $rowCount <= $totalRows; $rowCount++) {
            $table = $this->tableRenderer->render(
                $columns,
                array_slice($rowsArray, 0, $rowCount),
            );
            $candidate = $introSection.$table.$this->buildFooter($rowCount, $totalRows);

            if (mb_strlen($candidate) <= $maxTextLength) {
                $includedRows = $rowCount;
                $text = $candidate;
            } else {
                break;
            }
        }

        if ($includedRows > 0) {
            return new MaxMessageBuildResult(
                text: $text,
                totalRows: $totalRows,
                includedRows: $includedRows,
                truncated: $includedRows < $totalRows,
            );
        }

        return $this->buildWithTruncatedCells(
            introSection: $introSection,
            columns: $columns,
            rowsArray: $rowsArray,
            maxTextLength: $maxTextLength,
        );
    }

    /**
     * @param  iterable<int, array<string, int|string|null>>  $rows
     * @return array<int, array<string, int|string|null>>
     */
    private function normalizeRows(iterable $rows): array
    {
        if ($rows instanceof Traversable) {
            return iterator_to_array($rows, preserve_keys: false);
        }

        return array_values($rows);
    }

    private function formatIntroSection(string $intro): string
    {
        $intro = trim($intro);

        if ($intro === '') {
            return '';
        }

        return $intro."\n\n";
    }

    private function buildFooter(int $includedRows, int $totalRows): string
    {
        return "\n\n".sprintf(self::FOOTER_TEMPLATE, $includedRows, $totalRows);
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<int, array<string, int|string|null>>  $rowsArray
     */
    private function buildWithTruncatedCells(
        string $introSection,
        array $columns,
        array $rowsArray,
        int $maxTextLength,
    ): MaxMessageBuildResult {
        $totalRows = count($rowsArray);

        for ($maxCellLength = 500; $maxCellLength >= 10; $maxCellLength = (int) ($maxCellLength / 2)) {
            $truncatedRow = $this->truncateRowValues($columns, $rowsArray[0], $maxCellLength);
            $table = $this->tableRenderer->render($columns, [$truncatedRow]);
            $text = $introSection.$table.$this->buildFooter(1, $totalRows);

            if (mb_strlen($text) <= $maxTextLength) {
                return new MaxMessageBuildResult(
                    text: $text,
                    totalRows: $totalRows,
                    includedRows: 1,
                    truncated: true,
                );
            }
        }

        $table = $this->tableRenderer->render($columns, []);
        $text = $introSection.$table.$this->buildFooter(0, $totalRows);

        return new MaxMessageBuildResult(
            text: $this->ensureWithinLimit($text, $maxTextLength),
            totalRows: $totalRows,
            includedRows: 0,
            truncated: true,
        );
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<string, int|string|null>  $row
     * @return array<string, string>
     */
    private function truncateRowValues(array $columns, array $row, int $maxCellLength): array
    {
        $truncated = [];

        foreach ($columns as $column) {
            $truncated[$column['key']] = $this->truncateCellValue(
                (string) ($row[$column['key']] ?? ''),
                $maxCellLength,
            );
        }

        return $truncated;
    }

    private function truncateCellValue(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        if ($maxLength <= 1) {
            return mb_substr($value, 0, $maxLength);
        }

        return mb_substr($value, 0, $maxLength - 1).'…';
    }

    private function ensureWithinLimit(string $text, int $maxTextLength): string
    {
        if (mb_strlen($text) <= $maxTextLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxTextLength);
    }
}
