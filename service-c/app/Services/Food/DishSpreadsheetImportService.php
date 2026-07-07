<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishAdminServiceInterface;
use App\Contracts\Food\MenuCategoryRepositoryInterface;
use App\DTO\Food\DishImportResultDto;
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
        $importedCount = 0;

        /** @var list<array{row: int, message: string}> $errors */
        $errors = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $nameCell = $sheet->getCell('A'.$rowNumber)->getCalculatedValue();
            $priceCell = $sheet->getCell('B'.$rowNumber)->getCalculatedValue();

            if ($this->isEmptyRow($nameCell, $priceCell)) {
                continue;
            }

            try {
                $row = $this->rowParser->parse($nameCell, $priceCell);
                $this->dishAdminService->importSpreadsheetRow($row, $menuCategoryId);
                $importedCount++;
            } catch (FoodDomainException $exception) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return new DishImportResultDto($importedCount, $errors);
    }

    private function isEmptyRow(mixed $nameCell, mixed $priceCell): bool
    {
        return trim((string) $nameCell) === '' && trim((string) $priceCell) === '';
    }
}
