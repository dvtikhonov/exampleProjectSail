<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use App\Contracts\Food\PhotoText\PhotoTextScheduleApplierInterface;
use App\Contracts\Food\PhotoText\PhotoTextScheduleMatcherInterface;
use App\Contracts\Food\PhotoText\PhotoTextSchedulePlacementServiceInterface;
use App\DTO\Food\PhotoText\PhotoTextScheduleResultDto;

/**
 * Facade: exact match имён графика PhotoText и запись через DishAvailabilityScheduleWriterInterface.
 * Apply заменяет график в окне целиком (фото — источник истины): старые даты в scope удаляются.
 *
 * Делегирует в {@see PhotoTextScheduleMatcherInterface} и {@see PhotoTextScheduleApplierInterface}.
 */
class PhotoTextSchedulePlacementService implements PhotoTextSchedulePlacementServiceInterface
{
    public function __construct(
        private readonly PhotoTextScheduleMatcherInterface $matcher,
        private readonly PhotoTextScheduleApplierInterface $applier,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function match(
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): PhotoTextScheduleResultDto {
        return $this->matcher->match($restaurantId, $categoryIds, $dateFrom, $dateTo, $entries);
    }

    /**
     * {@inheritDoc}
     *
     * Фото однозначно задаёт график на окно: в scope (указанные category_ids или все
     * категории ресторана) даты в диапазоне заменяются; блюда вне entries получают [].
     */
    public function apply(
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): PhotoTextScheduleResultDto {
        return $this->applier->apply($restaurantId, $categoryIds, $dateFrom, $dateTo, $entries);
    }
}
