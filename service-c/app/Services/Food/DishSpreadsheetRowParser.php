<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\DTO\Food\ImportDishRowDto;
use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;

/**
 * Парсер строк XLS/XLSX для импорта блюд (колонки A — «Название. 100г», B — цена).
 */
class DishSpreadsheetRowParser
{
    private const string NAME_WEIGHT_PATTERN = '/^(.*)\.\s*(\d+)\s*г\s*$/ui';

    /**
     * @param  list<list<mixed>>  $rows
     * @return array{rows: list<ImportDishRowDto>, errors: list<array{row: int, message: string}>}
     */
    public function parseRows(array $rows): array
    {
        $parsedRows = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            if ($rowNumber === 1) {
                continue;
            }

            $columnA = $this->cellToString($row[0] ?? null);
            $columnB = $this->cellToString($row[1] ?? null);

            if ($columnA === '' && $columnB === '') {
                continue;
            }

            $nameWeightMatch = preg_match(self::NAME_WEIGHT_PATTERN, $columnA, $matches);

            if ($nameWeightMatch !== 1) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Неверный формат колонки A. Ожидается «Название. 100г».',
                ];

                continue;
            }

            $price = $this->parsePrice($columnB);

            if ($price === null) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Неверный формат колонки B. Ожидается цена в рублях.',
                ];

                continue;
            }

            $parsedRows[] = new ImportDishRowDto(
                name: trim($matches[1]),
                weight: (string) (int) $matches[2],
                weightUnit: DishWeightUnit::Gram,
                price: $price,
                vatRate: DishVatRate::Exempt,
                isAvailable: true,
                description: null,
            );
        }

        return [
            'rows' => $parsedRows,
            'errors' => $errors,
        ];
    }

    private function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        return trim((string) $value);
    }

    private function parsePrice(string $rawValue): ?string
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($rawValue));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
