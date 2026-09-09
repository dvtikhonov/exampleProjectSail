<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\ImportDishRowDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Пакетная запись блюд из строк spreadsheet-импорта.
 */
interface DishBulkImportWriterInterface
{
    /**
     * Пакетный импорт строк: при точном совпадении названия обновляет только цену.
     *
     * @param  list<ImportDishRowDto>  $rows
     *
     * @throws FoodDomainException
     */
    public function importSpreadsheetRows(array $rows, int $menuCategoryId): int;
}
