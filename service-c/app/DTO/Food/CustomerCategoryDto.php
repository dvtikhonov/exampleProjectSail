<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Категория клиента доставки (id и название).
 */
readonly class CustomerCategoryDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    /**
     * Преобразует категорию клиента в массив для JSON-ответа API.
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
