<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use RuntimeException;

/**
 * Генерация XLS-фикстур для тестов импорта блюд.
 */
class DishImportSpreadsheetFactory
{
    private const string FIXTURES_DIR = 'tests/fixtures';

    /**
     * @param  list<array{0: string, 1: string|int|float}>  $dataRows  Строки данных без заголовка
     */
    public static function xls(array $dataRows, string $filename = 'dishes-import.xls'): UploadedFile
    {
        $path = self::createSpreadsheetFile($dataRows);

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.ms-excel',
            null,
            true,
        );
    }

    public static function validSample(): UploadedFile
    {
        return self::fromFixture('dishes-import-valid.xls');
    }

    public static function invalidSample(): UploadedFile
    {
        return self::fromFixture('dishes-import-invalid.xls');
    }

    /**
     * @param  list<array{0: string, 1: string|int|float}>  $dataRows
     */
    private static function createSpreadsheetFile(array $dataRows): string
    {
        if (! class_exists(Spreadsheet::class)) {
            throw new RuntimeException('PhpSpreadsheet is required to build custom import spreadsheets in tests.');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Блюдо');
        $sheet->setCellValue('B1', 'Цена');

        foreach ($dataRows as $index => $row) {
            $rowNumber = $index + 2;
            $sheet->setCellValue('A'.$rowNumber, $row[0]);
            $sheet->setCellValue('B'.$rowNumber, $row[1]);
        }

        $path = sys_get_temp_dir().'/'.uniqid('dish-import-', true).'.xls';
        (new Xls($spreadsheet))->save($path);

        return $path;
    }

    private static function fromFixture(string $filename): UploadedFile
    {
        $sourcePath = base_path(self::FIXTURES_DIR.'/'.$filename);

        if (! is_file($sourcePath)) {
            throw new RuntimeException("Fixture not found: {$sourcePath}");
        }

        $path = sys_get_temp_dir().'/'.uniqid('dish-import-fixture-', true).'.xls';
        copy($sourcePath, $path);

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.ms-excel',
            null,
            true,
        );
    }
}
