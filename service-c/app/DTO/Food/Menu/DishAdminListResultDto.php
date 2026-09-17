<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

/**
 * Список блюд для админки с признаком обрезки по жёсткому лимиту.
 */
readonly class DishAdminListResultDto
{
    /**
     * @param  list<DishRecord>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public bool $truncated,
    ) {}
}
