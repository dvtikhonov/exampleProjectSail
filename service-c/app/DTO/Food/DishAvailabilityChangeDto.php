<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Изменение доступности одного блюда в графике.
 */
readonly class DishAvailabilityChangeDto
{
    /**
     * @param  list<string>  $dates
     */
    public function __construct(
        public int $dishId,
        public array $dates,
    ) {}
}
