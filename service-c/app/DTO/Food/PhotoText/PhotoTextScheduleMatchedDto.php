<?php

declare(strict_types=1);

namespace App\DTO\Food\PhotoText;

/**
 * Бесспорно сматченная позиция графика PhotoText.
 */
readonly class PhotoTextScheduleMatchedDto
{
    /**
     * @param  list<string>  $dates
     */
    public function __construct(
        public string $rawTitle,
        public int $dishId,
        public string $dishName,
        public int $categoryId,
        public string $categoryName,
        public array $dates,
    ) {}

    /**
     * @return array{
     *     raw_title: string,
     *     dish_id: int,
     *     dish_name: string,
     *     category_id: int,
     *     category_name: string,
     *     dates: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'raw_title' => $this->rawTitle,
            'dish_id' => $this->dishId,
            'dish_name' => $this->dishName,
            'category_id' => $this->categoryId,
            'category_name' => $this->categoryName,
            'dates' => $this->dates,
        ];
    }
}
