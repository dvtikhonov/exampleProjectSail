<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

/**
 * Краткая доменная проекция ресторана без Eloquent.
 */
readonly class RestaurantSummaryRecord
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $isActive,
        public string $address = '',
    ) {}
}
