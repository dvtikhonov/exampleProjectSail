<?php

declare(strict_types=1);

namespace App\DTO\Max;

/**
 * Результат подготовки меню для k6 (включение is_available).
 */
readonly class LoadTestPrepareMenuResultDto
{
    /**
     * @param  list<int>  $restaurantIds
     */
    public function __construct(
        public int $dishesEnabled,
        public array $restaurantIds,
    ) {}
}
