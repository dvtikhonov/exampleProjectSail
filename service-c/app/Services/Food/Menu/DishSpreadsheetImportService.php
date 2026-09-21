<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishBulkImportWriterInterface;
use App\Contracts\Food\Menu\DishSpreadsheetImportServiceInterface;
use App\Contracts\Food\Menu\DishSpreadsheetRowsReaderInterface;
use App\Contracts\Food\Menu\MenuCategoryReadRepositoryInterface;
use App\DTO\Food\Menu\DishImportResultDto;
use App\DTO\Food\Menu\ImportDishRowDto;
use App\DTO\Shared\UploadedFileDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Импорт блюд из XLS/XLSX в выбранную категорию меню.
 */
class DishSpreadsheetImportService implements DishSpreadsheetImportServiceInterface
{
    public function __construct(
        private readonly DishBulkImportWriterInterface $bulkImportWriter,
        private readonly DishSpreadsheetRowParser $rowParser,
        private readonly DishSpreadsheetRowsReaderInterface $rowsReader,
        private readonly MenuCategoryReadRepositoryInterface $menuCategoryRepository,
    ) {}

    /**
     * Импортирует блюда из spreadsheet-файла.
     *
     * @throws FoodDomainException
     */
    public function import(UploadedFileDto $file, int $menuCategoryId): DishImportResultDto
    {
        if ($this->menuCategoryRepository->findById($menuCategoryId) === null) {
            throw new FoodDomainException('Категория меню не найдена.', 422);
        }

        $path = $file->path;

        if ($path === '' || ! is_readable($path)) {
            throw new FoodDomainException('Файл таблицы недействителен.', 422);
        }

        $sheetRows = $this->rowsReader->readRows($path);

        /** @var list<ImportDishRowDto> $validRows */
        $validRows = [];

        /** @var list<array{row: int, message: string}> $errors */
        $errors = [];

        foreach ($sheetRows as $sheetRow) {
            try {
                $validRows[] = $this->rowParser->parse($sheetRow['name'], $sheetRow['price']);
            } catch (FoodDomainException $exception) {
                $errors[] = [
                    'row' => $sheetRow['row'],
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $importedCount = 0;

        if ($validRows !== []) {
            $importedCount = $this->bulkImportWriter->importSpreadsheetRows($validRows, $menuCategoryId);
        }

        return new DishImportResultDto($importedCount, $errors);
    }
}
