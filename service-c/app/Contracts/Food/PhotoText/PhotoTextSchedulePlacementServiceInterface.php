<?php

declare(strict_types=1);

namespace App\Contracts\Food\PhotoText;

use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleResultDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Сверка имён и запись графика производства PhotoText в рамках ресторана.
 */
interface PhotoTextSchedulePlacementServiceInterface
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

    /**
     * Match + полная замена графика в окне (фото — источник истины).
     * Scope: указанные category_ids или все категории ресторана; блюда вне entries очищаются.
     * Пустой matched — отчёт без applied (контроллер 422).
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
