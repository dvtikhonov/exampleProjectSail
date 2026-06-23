<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Краткие данные ресторана для списка в mini-app.
 */
readonly class RestaurantSummaryDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $address,
    ) {}

    /**
     * Преобразует ресторан в массив для JSON-ответа API.
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
        ];
    }
}
