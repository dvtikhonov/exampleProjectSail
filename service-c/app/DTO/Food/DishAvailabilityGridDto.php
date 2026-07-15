<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Данные графика доступности блюд для админки.
 */
readonly class DishAvailabilityGridDto
{
    /**
     * @param  list<array{id: int, name: string, is_available: bool}>  $dishes
     * @param  list<string>  $dates
     * @param  array<string, list<string>>  $schedule
     */
    public function __construct(
        public array $dishes,
        public array $dates,
        public array $schedule,
        public string $editableFrom,
    ) {}

    /**
     * Преобразует сетку доступности блюд в массив.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dishes' => $this->dishes,
            'dates' => $this->dates,
            'schedule' => $this->schedule,
            'editable_from' => $this->editableFrom,
        ];
    }
}
