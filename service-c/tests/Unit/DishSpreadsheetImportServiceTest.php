<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Menu\DishBulkImportWriterInterface;
use App\Contracts\Food\Menu\DishSpreadsheetRowsReaderInterface;
use App\Contracts\Food\Menu\MenuCategoryReadRepositoryInterface;
use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\DTO\Food\Menu\ImportDishRowDto;
use App\DTO\Food\Menu\MenuCategoryRecord;
use App\DTO\Shared\UploadedFileDto;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\Menu\DishSpreadsheetImportService;
use App\Services\Food\Menu\DishSpreadsheetRowParser;
use Tests\TestCase;

class DishSpreadsheetImportServiceTest extends TestCase
{
    /** Импорт парсит строки reader и передаёт валидные DTO в writer. */
    public function test_import_parses_reader_rows_and_writes_valid_dtos(): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'dish-import-unit-');
        $this->assertNotFalse($tmpPath);
        file_put_contents($tmpPath, 'placeholder');

        try {
            $categoryRepository = $this->createMock(MenuCategoryReadRepositoryInterface::class);
            $categoryRepository->expects($this->once())
                ->method('findById')
                ->with(7)
                ->willReturn(new MenuCategoryRecord(
                    id: 7,
                    restaurantId: 1,
                    name: 'Супы',
                    sortOrder: 0,
                    isComboAvailable: false,
                ));

            $rowsReader = $this->createMock(DishSpreadsheetRowsReaderInterface::class);
            $rowsReader->expects($this->once())
                ->method('readRows')
                ->with($tmpPath)
                ->willReturn([
                    ['row' => 2, 'name' => 'Борщ. 300г', 'price' => '250'],
                    ['row' => 3, 'name' => 'Неверный формат', 'price' => '100'],
                ]);

            $bulkWriter = $this->createMock(DishBulkImportWriterInterface::class);
            $bulkWriter->expects($this->once())
                ->method('importSpreadsheetRows')
                ->with(
                    $this->callback(function (array $rows): bool {
                        return count($rows) === 1
                            && $rows[0] instanceof ImportDishRowDto
                            && $rows[0]->name === 'Борщ'
                            && $rows[0]->price === '250.00';
                    }),
                    7,
                )
                ->willReturn(1);

            $service = new DishSpreadsheetImportService(
                $bulkWriter,
                new DishSpreadsheetRowParser($this->app->make(FoodMoneyFormatterInterface::class)),
                $rowsReader,
                $categoryRepository,
            );

            $result = $service->import(
                new UploadedFileDto($tmpPath, 'menu.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 10),
                7,
            );

            $this->assertSame(1, $result->importedCount);
            $this->assertCount(1, $result->errors);
            $this->assertSame(3, $result->errors[0]['row']);
            $this->assertStringContainsString('Колонка A', $result->errors[0]['message']);
        } finally {
            @unlink($tmpPath);
        }
    }

    /** Отсутствующая категория меню выбрасывает DomainException. */
    public function test_missing_category_throws_domain_exception(): void
    {
        $categoryRepository = $this->createMock(MenuCategoryReadRepositoryInterface::class);
        $categoryRepository->method('findById')->willReturn(null);

        $service = new DishSpreadsheetImportService(
            $this->createMock(DishBulkImportWriterInterface::class),
            new DishSpreadsheetRowParser($this->app->make(FoodMoneyFormatterInterface::class)),
            $this->createMock(DishSpreadsheetRowsReaderInterface::class),
            $categoryRepository,
        );

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Категория меню не найдена');

        $service->import(
            new UploadedFileDto('/tmp/missing.xlsx', 'menu.xlsx', 'application/octet-stream', 0),
            99,
        );
    }
}
