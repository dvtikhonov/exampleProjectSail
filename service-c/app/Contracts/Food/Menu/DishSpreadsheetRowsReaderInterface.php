<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\Exceptions\Food\FoodDomainException;

/**
 * Чтение строк XLS/XLSX для импорта блюд (колонки A/B, со 2-й строки).
 */
interface DishSpreadsheetRowsReaderInterface
{
    /**
     * Читает непустые строки таблицы по пути к файлу.
     *
     * @return list<array{row: int, name: mixed, price: mixed}>
     *
     * @throws FoodDomainException
     */
    public function readRows(string $path): array;
}
