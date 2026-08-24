<?php

declare(strict_types=1);

namespace App\DTO\Food\PhotoText;

/**
 * Каноническая позиция агента PhotoText: одно блюдо, без серверного split по «/».
 */
readonly class PhotoTextAgentItemDto
{
    public function __construct(
        public string $name,
        public int $quantity,
        public ?string $comboRef = null,
    ) {}
}
