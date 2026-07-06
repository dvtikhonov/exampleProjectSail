<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Пакетное обновление графика доступности блюд.
 */
readonly class DishAvailabilityUpdateDto
{
    /**
     * @param  list<DishAvailabilityChangeDto>  $changes
     */
    public function __construct(
        public int $restaurantId,
        public int $categoryId,
        public array $changes,
        public ?string $dateFrom,
        public ?string $dateTo,
    ) {}
}
