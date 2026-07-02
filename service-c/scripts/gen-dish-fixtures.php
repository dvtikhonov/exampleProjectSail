<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

function save(array $rows, string $path): void
{
    $s = new Spreadsheet;
    $sheet = $s->getActiveSheet();
    $sheet->setCellValue('A1', 'Блюдо');
    $sheet->setCellValue('B1', 'Цена');
    foreach ($rows as $i => $row) {
        $n = $i + 2;
        $sheet->setCellValue('A' . $n, $row[0]);
        $sheet->setCellValue('B' . $n, $row[1]);
    }
    (new Xls($s))->save($path);
}

mkdir('tests/fixtures', 0777, true);
save([['Борщ.350г', '150'], ['Пицца Маргарита.450г', '520,50']], 'tests/fixtures/dishes-import-valid.xls');
save([['Борщ.350г', '150'], ['Неверный формат', '200']], 'tests/fixtures/dishes-import-invalid.xls');
echo 'done', PHP_EOL;
