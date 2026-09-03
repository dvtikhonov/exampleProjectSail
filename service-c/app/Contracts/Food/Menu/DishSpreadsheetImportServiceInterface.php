<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\DishImportResultDto;
use App\DTO\Shared\UploadedFileDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Импорт блюд из XLS/XLSX в выбранную категорию меню.
 */
interface DishSpreadsheetImportServiceInterface
{
    /**
     * Импортирует блюда из spreadsheet-файла.
     *
     * @throws FoodDomainException
     */
    public function import(UploadedFileDto $file, int $menuCategoryId): DishImportResultDto;
}
