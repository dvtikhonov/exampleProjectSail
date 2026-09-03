<?php

declare(strict_types=1);

namespace App\Contracts\Food\PhotoText;

use App\DTO\Food\PhotoText\PhotoTextDishNameMatchResultDto;

/**
 * Exact match имени блюда PhotoText в каталоге ресторана (опционально — в scope категорий).
 */
interface PhotoTextDishNameMatcherInterface
{
    /**
     * @param  list<int>|null  $categoryIds  null — без фильтра по категориям
     */
    public function match(
        string $searchName,
        int $restaurantId,
        ?array $categoryIds = null,
    ): PhotoTextDishNameMatchResultDto;
}
