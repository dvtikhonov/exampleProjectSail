<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

/**
 * Активный ресторан с категориями и блюдами для клиентского меню.
 */
readonly class RestaurantWithMenuRecord
{
    /**
     * @param  list<MenuCategoryRecord>  $menuCategories
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $menuCategories,
    ) {}
}
