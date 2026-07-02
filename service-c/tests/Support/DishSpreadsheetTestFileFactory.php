<?php

declare(strict_types=1);

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Генерация XLSX-файлов для тестов импорта блюд.
 */
class DishSpreadsheetTestFileFactory
{
    /**
     * @param  list<array{0: string, 1: string|float|int}>  $dataRows
     */
    public static function createXlsx(array $dataRows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Название');
        $sheet->setCellValue('B1', 'Цена');

        foreach ($dataRows as $index => $row) {
            $rowNumber = $index + 2;
            $sheet->setCellValue('A'.$rowNumber, $row[0]);
            $sheet->setCellValue('B'.$rowNumber, $row[1]);
        }

        $path = tempnam(sys_get_temp_dir(), 'dish-import-');
        $xlsxPath = $path.'.xlsx';

        if (! rename($path, $xlsxPath)) {
            throw new \RuntimeException('Не удалось создать временный XLSX-файл.');
        }

        (new Xlsx($spreadsheet))->save($xlsxPath);

        return $xlsxPath;
    }
}
