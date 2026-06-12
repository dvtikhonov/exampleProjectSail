<?php

declare(strict_types=1);

namespace App\DTO\Food;

readonly class RestaurantSummaryDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $address,
    ) {}

    /**
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
