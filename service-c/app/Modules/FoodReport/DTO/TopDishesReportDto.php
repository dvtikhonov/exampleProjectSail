<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\DTO;

/**
 * Отчёт «топ позиций» по дням.
 */
readonly class TopDishesReportDto
{
    /**
     * @param  list<TopDishDayRowDto>  $rows
     */
    public function __construct(
        public array $rows,
    ) {}

    /**
     * Группирует плоские строки в days[].items[].
     *
     * @return array{
     *     days: list<array{
     *         date: string,
     *         items: list<array{dish_id: int|null, dish_name: string, quantity: int, amount: string}>
     *     }>
     * }
     */
    public function toArray(): array
    {
        /** @var array<string, list<array{dish_id: int|null, dish_name: string, quantity: int, amount: string}>> $byDate */
        $byDate = [];

        foreach ($this->rows as $row) {
            $byDate[$row->date][] = $row->toItemArray();
        }

        $days = [];
        foreach ($byDate as $date => $items) {
            $days[] = [
                'date' => $date,
                'items' => $items,
            ];
        }

        return ['days' => $days];
    }
}
