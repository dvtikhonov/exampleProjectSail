<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\MenuCategoryWeekdayOffsetDto;

/**
 * Репозиторий смещений доступности категорий меню (max_menu_category_availability_offsets).
 */
interface MenuCategoryAvailabilityOffsetRepositoryInterface
{
    /**
     * Есть ли хотя бы одна запись смещения в таблице.
     */
    public function hasAnyOffsets(): bool;

    /**
     * Все offset_days по ISO-дню недели (1=Пн … 7=Вс) по всем категориям.
     *
     * @return list<int>
     */
    public function listOffsetDaysForWeekday(int $weekday): array;

    /**
     * Смещения категорий меню на ISO-день недели (1=Пн … 7=Вс).
     *
     * @return list<MenuCategoryWeekdayOffsetDto>
     */
    public function listCategoryOffsetsForWeekday(int $weekday): array;
}
