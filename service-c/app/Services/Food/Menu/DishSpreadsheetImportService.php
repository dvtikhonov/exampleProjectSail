<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishAdminServiceInterface;
use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\DTO\Food\Menu\DishImportResultDto;
use App\DTO\Food\Menu\ImportDishRowDto;
use App\Exceptions\Food\FoodDomainException;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Импорт блюд из XLS/XLSX в выбранную категорию меню.
 */
class DishSpreadsheetImportService
{
    public function __construct(
        private readonly DishAdminServiceInterface $dishAdminService,
        private readonly DishSpreadsheetRowParser $rowParser,
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
    ) {}

    /**
     * Импортирует блюда из spreadsheet-файла.
     *
     * @throws FoodDomainException
     */
    public function import(UploadedFile $file, int $menuCategoryId): DishImportResultDto
    {
        if ($this->menuCategoryRepository->findById($menuCategoryId) === null) {
            throw new FoodDomainException('Категория меню не найдена.', 422);
        }

        $path = $file->getRealPath();

        if ($path === false) {
            throw new FoodDomainException('Файл таблицы недействителен.', 422);
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable) {
            throw new FoodDomainException('Не удалось прочитать файл таблицы.', 422);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        /** @var list<ImportDishRowDto> $validRows */
        $validRows = [];

        /** @var list<array{row: int, message: string}> $errors */
        $errors = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $nameCell = $sheet->getCell('A'.$rowNumber)->getCalculatedValue();
            $priceCell = $sheet->getCell('B'.$rowNumber)->getCalculatedValue();

            if ($this->isEmptyRow($nameCell, $priceCell)) {
                continue;
            }

            try {
                $validRows[] = $this->rowParser->parse($nameCell, $priceCell);
            } catch (FoodDomainException $exception) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $importedCount = 0;

        if ($validRows !== []) {
            $importedCount = $this->dishAdminService->importSpreadsheetRows($validRows, $menuCategoryId);
        }

        return new DishImportResultDto($importedCount, $errors);
    }

    /**
     * Проверяет, является ли строка таблицы пустой.
     */
    private function isEmptyRow(mixed $nameCell, mixed $priceCell): bool
    {
        return trim((string) $nameCell) === '' && trim((string) $priceCell) === '';
    }
}
