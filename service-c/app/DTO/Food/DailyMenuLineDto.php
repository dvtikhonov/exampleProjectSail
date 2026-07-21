<?php

declare(strict_types=1);

namespace App\DTO\Food;

use App\Enums\Food\DailyMenuLineType;

/**
 * Позиция ежедневного меню: обычное блюдо или комбо-пара.
 */
readonly class DailyMenuLineDto
{
    /**
     * @param  list<DailyMenuDishPartDto>  $parts
     */
    public function __construct(
        public DailyMenuLineType $type,
        public array $parts,
        public int $quantity = 1,
    ) {}
}
