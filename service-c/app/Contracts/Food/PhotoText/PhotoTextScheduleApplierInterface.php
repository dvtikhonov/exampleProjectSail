<?php

declare(strict_types=1);

namespace App\Contracts\Food\PhotoText;

use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleResultDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Match + полная замена графика PhotoText в окне (фото — источник истины).
 */
interface PhotoTextScheduleApplierInterface
{
    /**
     * Match + полная замена графика в окне.
     * Scope: указанные category_ids или все категории ресторана; блюда вне entries очищаются.
     * Пустой matched — отчёт без applied.
     *
     * @param  list<int>|null  $categoryIds  null — все категории ресторана
     * @param  list<PhotoTextScheduleEntryDto>  $entries
     *
     * @throws FoodDomainException даты/категория/блюда (как у admin sync)
     */
    public function apply(
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): PhotoTextScheduleResultDto;
}
