<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\DTO;

/**
 * Позиция топа блюд за один день (агрегат по dish).
 */
readonly class TopDishDayRowDto
{
    public function __construct(
        public string $date,
        public ?int $dishId,
        public string $dishName,
        public int $quantity,
        public string $amount,
    ) {}

    /**
     * @return array{dish_id: int|null, dish_name: string, quantity: int, amount: string}
     */
    public function toItemArray(): array
    {
        return [
            'dish_id' => $this->dishId,
            'dish_name' => $this->dishName,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
        ];
    }
}
