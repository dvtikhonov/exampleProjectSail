<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Menu\DishSpreadsheetRowsReaderInterface;
use App\Exceptions\Food\FoodDomainException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * PhpSpreadsheet-адаптер чтения строк импорта блюд.
 */
final class PhpSpreadsheetDishRowsReader implements DishSpreadsheetRowsReaderInterface
{
    /**
     * {@inheritDoc}
     */
    public function readRows(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable) {
            throw new FoodDomainException('Не удалось прочитать файл таблицы.', 422);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        /** @var list<array{row: int, name: mixed, price: mixed}> $rows */
        $rows = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $nameCell = $sheet->getCell('A'.$rowNumber)->getCalculatedValue();
            $priceCell = $sheet->getCell('B'.$rowNumber)->getCalculatedValue();

            if ($this->isEmptyRow($nameCell, $priceCell)) {
                continue;
            }

            $rows[] = [
                'row' => $rowNumber,
                'name' => $nameCell,
                'price' => $priceCell,
            ];
        }

        return $rows;
    }

    /**
     * Проверяет, является ли строка таблицы пустой.
     */
    private function isEmptyRow(mixed $nameCell, mixed $priceCell): bool
    {
        return trim((string) $nameCell) === '' && trim((string) $priceCell) === '';
    }
}
