<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

/**
 * Правило смещения доступности блюд категории по дням недели.
 */
readonly class MenuCategoryAvailabilityOffsetDto
{
    /**
     * @param  list<int>  $weekdays  Дни недели ISO: 1=Пн … 7=Вс
     */
    public function __construct(
        public array $weekdays,
        public int $offsetDays,
    ) {}

    /**
     * Преобразует правило смещения в массив для API.
     *
     * @return array{weekdays: list<int>, offset_days: int}
     */
    public function toArray(): array
    {
        return [
            'weekdays' => $this->weekdays,
            'offset_days' => $this->offsetDays,
        ];
    }
}
