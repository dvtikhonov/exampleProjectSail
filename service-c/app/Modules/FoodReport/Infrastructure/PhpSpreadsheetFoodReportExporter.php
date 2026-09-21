<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Infrastructure;

use App\Modules\FoodReport\Contracts\FoodReportQueryServiceInterface;
use App\Modules\FoodReport\Contracts\FoodReportSpreadsheetExporterInterface;
use App\Modules\FoodReport\DTO\ReportFilterDto;
use App\Modules\FoodReport\DTO\RevenueDayRowDto;
use App\Modules\FoodReport\DTO\RevenueReportDto;
use App\Modules\FoodReport\DTO\TopDishDayRowDto;
use App\Modules\FoodReport\DTO\TopDishesReportDto;
use App\Modules\FoodReport\Enums\ReportType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Экспорт отчётов Food в .xlsx через PhpSpreadsheet (один лист по report_type).
 *
 * Tech-адаптер Infrastructure: не инжектить из Delivery напрямую — только через
 * {@see FoodReportSpreadsheetExporterInterface}.
 */
final class PhpSpreadsheetFoodReportExporter implements FoodReportSpreadsheetExporterInterface
{
    private const SHEET_REVENUE = 'Выручка';

    private const SHEET_TOP_DISHES = 'Топ позиций';

    public function __construct(
        private readonly FoodReportQueryServiceInterface $queryService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function export(ReportType $type, ReportFilterDto $filter, int $limitPerDay = 20): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        match ($type) {
            ReportType::Revenue => $this->fillRevenueSheet(
                $sheet,
                $this->queryService->revenue($filter),
            ),
            ReportType::TopDishes => $this->fillTopDishesSheet(
                $sheet,
                $this->queryService->topDishes($filter, $limitPerDay),
            ),
        };

        return $this->writeToBinary($spreadsheet);
    }

    private function fillRevenueSheet(Worksheet $sheet, RevenueReportDto $report): void
    {
        $sheet->setTitle(self::SHEET_REVENUE);
        $sheet->fromArray(
            ['Дата', 'Кол-во', 'Средний чек', 'Сумма'],
            null,
            'A1',
        );

        // days уже в порядке date ASC из FoodReportQueryService.
        $rowNumber = 2;
        foreach ($report->days as $day) {
            /** @var RevenueDayRowDto $day */
            $this->writeRevenueDataRow(
                $sheet,
                $rowNumber,
                $day->date,
                $day->ordersCount,
                $day->averageCheck,
                $day->amount,
            );
            $rowNumber++;
        }

        $this->writeRevenueDataRow(
            $sheet,
            $rowNumber,
            'Итого',
            $report->metaOrdersCount,
            $report->metaAverageCheck,
            $report->metaAmount,
        );
    }

    /**
     * Перекрёстная таблица: строки — блюда, колонки — даты (asc), на дату Кол-во + Сумма.
     */
    private function fillTopDishesSheet(Worksheet $sheet, TopDishesReportDto $report): void
    {
        $sheet->setTitle(self::SHEET_TOP_DISHES);

        /** @var array<string, true> $datesSet */
        $datesSet = [];
        /** @var array<string, string> $dishNamesByKey */
        $dishNamesByKey = [];
        /** @var array<string, array<string, array{quantity: int, amount: string}>> $valuesByDishAndDate */
        $valuesByDishAndDate = [];

        foreach ($report->rows as $row) {
            /** @var TopDishDayRowDto $row */
            $datesSet[$row->date] = true;
            $dishKey = $row->dishId !== null
                ? 'id:'.$row->dishId
                : 'name:'.$row->dishName;
            $dishNamesByKey[$dishKey] = $row->dishName;
            $valuesByDishAndDate[$dishKey][$row->date] = [
                'quantity' => $row->quantity,
                'amount' => $row->amount,
            ];
        }

        $orderedDates = array_keys($datesSet);
        sort($orderedDates, SORT_STRING);

        uasort(
            $dishNamesByKey,
            static fn (string $left, string $right): int => $left <=> $right,
        );

        $sheet->setCellValue('A1', 'Наименование блюд');
        $sheet->mergeCells('A1:A2');

        $columnIndex = 2;
        foreach ($orderedDates as $date) {
            $qtyColumn = Coordinate::stringFromColumnIndex($columnIndex);
            $amountColumn = Coordinate::stringFromColumnIndex($columnIndex + 1);
            $sheet->setCellValue($qtyColumn.'1', $date);
            $sheet->mergeCells($qtyColumn.'1:'.$amountColumn.'1');
            $sheet->setCellValue($qtyColumn.'2', 'Кол-во');
            $sheet->setCellValue($amountColumn.'2', 'Сумма');
            $columnIndex += 2;
        }

        $rowNumber = 3;
        foreach ($dishNamesByKey as $dishKey => $dishName) {
            $sheet->setCellValue('A'.$rowNumber, $dishName);
            $columnIndex = 2;
            foreach ($orderedDates as $date) {
                $qtyColumn = Coordinate::stringFromColumnIndex($columnIndex);
                $amountColumn = Coordinate::stringFromColumnIndex($columnIndex + 1);
                $cell = $valuesByDishAndDate[$dishKey][$date] ?? null;
                if ($cell !== null) {
                    $sheet->setCellValue($qtyColumn.$rowNumber, $cell['quantity']);
                    $sheet->setCellValueExplicit(
                        $amountColumn.$rowNumber,
                        $cell['amount'],
                        DataType::TYPE_STRING,
                    );
                }
                $columnIndex += 2;
            }
            $rowNumber++;
        }
    }

    private function writeRevenueDataRow(
        Worksheet $sheet,
        int $rowNumber,
        string $label,
        int $ordersCount,
        string $averageCheck,
        string $amount,
    ): void {
        $sheet->setCellValue('A'.$rowNumber, $label);
        $sheet->setCellValue('B'.$rowNumber, $ordersCount);
        $sheet->setCellValueExplicit('C'.$rowNumber, $averageCheck, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D'.$rowNumber, $amount, DataType::TYPE_STRING);
    }

    private function writeToBinary(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'food-report-');
        if ($path === false) {
            throw new RuntimeException('Не удалось создать временный файл для экспорта отчёта.');
        }

        $xlsxPath = $path.'.xlsx';
        if (! rename($path, $xlsxPath)) {
            @unlink($path);
            throw new RuntimeException('Не удалось подготовить временный .xlsx для экспорта отчёта.');
        }

        try {
            (new Xlsx($spreadsheet))->save($xlsxPath);
            $binary = file_get_contents($xlsxPath);
            if ($binary === false) {
                throw new RuntimeException('Не удалось прочитать временный .xlsx отчёта.');
            }

            return $binary;
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($xlsxPath);
        }
    }
}
