<?php

declare(strict_types=1);

namespace App\Contracts\Food\PhotoText;

use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleResultDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Exact match имён графика PhotoText (без записи в БД).
 */
interface PhotoTextScheduleMatcherInterface
{
    /**
     * Exact match имён в ресторане (±фильтр категорий); график не пишется.
     *
     * @param  list<int>|null  $categoryIds  null — весь каталог ресторана
     * @param  list<PhotoTextScheduleEntryDto>  $entries
     *
     * @throws FoodDomainException категория не принадлежит ресторану
     */
    public function match(
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): PhotoTextScheduleResultDto;
}
