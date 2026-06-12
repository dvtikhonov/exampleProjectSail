<?php

declare(strict_types=1);

namespace App\DTO\Food;

readonly class CartItemDto
{
    public function __construct(
        public int $id,
        public int $dishId,
        public string $dishName,
        public string $unitPrice,
        public int $quantity,
        public string $lineTotal,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'dish_id' => $this->dishId,
            'dish_name' => $this->dishName,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'line_total' => $this->lineTotal,
        ];
    }
}
