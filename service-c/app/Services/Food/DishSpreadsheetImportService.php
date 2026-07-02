<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\MenuCategoryRepositoryInterface;
use App\DTO\Food\DishImportResultDto;
use App\Exceptions\Food\FoodDomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Импорт блюд из XLS/XLSX в указанную категорию меню.
 */
class DishSpreadsheetImportService
{
    public function __construct(
        private readonly DishSpreadsheetRowParser $rowParser,
        private readonly DishAdminService $dishAdminService,
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
    ) {}

    /**
     * @throws FoodDomainException
     */
    public function import(UploadedFile $file, int $menuCategoryId): DishImportResultDto
    {
        $spreadsheet = IOFactory::load($file->getRealPath() ?: $file->getPathname());
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $parsed = $this->rowParser->parseRows($sheetRows);

        if ($parsed['errors'] !== []) {
            return new DishImportResultDto(
                importedCount: 0,
                errors: $parsed['errors'],
            );
        }

        $this->assertMenuCategoryExists($menuCategoryId);

        $importedCount = DB::transaction(function () use ($parsed, $menuCategoryId): int {
            $count = 0;

            foreach ($parsed['rows'] as $row) {
                $this->dishAdminService->importSpreadsheetRow($row, $menuCategoryId);
                $count++;
            }

            return $count;
        });

        return new DishImportResultDto(importedCount: $importedCount);
    }

    /**
     * @throws FoodDomainException
     */
    private function assertMenuCategoryExists(int $menuCategoryId): void
    {
        if ($this->menuCategoryRepository->findById($menuCategoryId) === null) {
            throw new FoodDomainException('Категория меню не найдена.', 422);
        }
    }
}
