<?php

declare(strict_types=1);

namespace App\DTO\Food;

readonly class MenuCategoryDto
{
    /**
     * @param  list<DishDto>  $dishes
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $dishes,
    ) {}

    /**
     * @return array<string, int|string|list<array<string, int|string|bool>>>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'dishes' => array_map(
                static fn (DishDto $dish): array => $dish->toArray(),
                $this->dishes,
            ),
        ];
    }
}
