<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

/**
 * Смещение доступности одной категории меню на конкретный ISO-день недели.
 */
readonly class MenuCategoryWeekdayOffsetDto
{
    /**
     * @param  int  $menuCategoryId  ID категории меню
     * @param  int  $offsetDays  Смещение в днях от текущей даты (0..30)
     */
    public function __construct(
        public int $menuCategoryId,
        public int $offsetDays,
    ) {}
}
