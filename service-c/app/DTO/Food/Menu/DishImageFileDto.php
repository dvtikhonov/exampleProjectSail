<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

/**
 * Локальный файл изображения блюда для отдачи на HTTP-границе.
 */
readonly class DishImageFileDto
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $absolutePath,
        public array $headers = [],
    ) {}
}
