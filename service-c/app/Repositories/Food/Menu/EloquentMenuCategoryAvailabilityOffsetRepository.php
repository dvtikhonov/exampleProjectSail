<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\MenuCategoryAvailabilityOffsetRepositoryInterface;
use App\DTO\Food\Menu\MenuCategoryWeekdayOffsetDto;
use App\Models\Food\MenuCategoryAvailabilityOffset;

/**
 * Eloquent-реализация репозитория смещений доступности категорий меню.
 */
class EloquentMenuCategoryAvailabilityOffsetRepository implements MenuCategoryAvailabilityOffsetRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function hasAnyOffsets(): bool
    {
        return MenuCategoryAvailabilityOffset::query()->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function listOffsetDaysForWeekday(int $weekday): array
    {
        return MenuCategoryAvailabilityOffset::query()
            ->where('weekday', $weekday)
            ->pluck('offset_days')
            ->map(static fn ($offsetDays): int => (int) $offsetDays)
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function listCategoryOffsetsForWeekday(int $weekday): array
    {
        return MenuCategoryAvailabilityOffset::query()
            ->where('weekday', $weekday)
            ->orderBy('menu_category_id')
            ->get(['menu_category_id', 'offset_days'])
            ->map(static fn (MenuCategoryAvailabilityOffset $row): MenuCategoryWeekdayOffsetDto => new MenuCategoryWeekdayOffsetDto(
                menuCategoryId: (int) $row->menu_category_id,
                offsetDays: (int) $row->offset_days,
            ))
            ->values()
            ->all();
    }
}
