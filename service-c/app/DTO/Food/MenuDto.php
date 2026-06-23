<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Полное меню ресторана: категории и блюда.
 */
readonly class MenuDto
{
    /**
     * @param  list<MenuCategoryDto>  $categories
     */
    public function __construct(
        public int $restaurantId,
        public string $restaurantName,
        public array $categories,
    ) {}

    /**
     * Преобразует меню в массив для JSON-ответа API.
     *
     * @return array<string, int|string|list<array<string, mixed>>>
     */
    public function toArray(): array
    {
        return [
            'restaurant_id' => $this->restaurantId,
            'restaurant_name' => $this->restaurantName,
            'categories' => array_map(
                static fn (MenuCategoryDto $category): array => $category->toArray(),
                $this->categories,
            ),
        ];
    }
}
