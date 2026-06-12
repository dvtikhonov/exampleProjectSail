<?php

declare(strict_types=1);

namespace App\DTO\Food;

readonly class DishDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $price,
        public bool $isAvailable,
        public ?string $imageUrl = null,
    ) {}

    /**
     * @return array<string, int|string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'is_available' => $this->isAvailable,
            'image_url' => $this->imageUrl,
        ];
    }
}
