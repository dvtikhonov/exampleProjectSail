<?php

declare(strict_types=1);

namespace App\DTO\Food\ComboCatalog;

/**
 * Позиция source для user-контекста промпта.
 * weightLabel на входе может быть компактным («120г»); билдер канонизирует.
 */
readonly class ComboCatalogPromptDishDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $price,
        public ?string $weightLabel = null,
        public ?string $lineKey = null,
    ) {}
}
