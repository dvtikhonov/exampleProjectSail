<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Категория меню ресторана со списком блюд.
 */
readonly class MenuCategoryDto
{
    /**
     * @param  list<DishDto>  $dishes
     */
    public function __construct(
        public int $id,
        public string $name,
        public bool $isComboAvailable,
        public array $dishes,
    ) {}

    /**
     * Преобразует категорию меню в массив для JSON-ответа API.
     *
     * @return array<string, int|string|bool|list<array<string, int|string|bool>>>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_combo_available' => $this->isComboAvailable,
            'dishes' => array_map(
                static fn (DishDto $dish): array => $dish->toArray(),
                $this->dishes,
            ),
        ];
    }
}
