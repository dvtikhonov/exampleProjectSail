<?php

declare(strict_types=1);

namespace App\DTO\Food\ComboCatalog;

/**
 * Готовый промпт для Cursor match: system из ENUM, user с динамическим контекстом.
 */
readonly class ComboCatalogPromptDto
{
    public function __construct(
        public string $system,
        public string $user,
    ) {}

    /**
     * @return array{system: string, user: string}
     */
    public function toArray(): array
    {
        return [
            'system' => $this->system,
            'user' => $this->user,
        ];
    }
}
