<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Контекст вызова LLM match для аудита (кто и какая сессия).
 */
readonly class BrisklySyncLlmCallContextDto
{
    public function __construct(
        public string $sessionId,
        public int $restaurantId,
        public ?int $createdByMaxUserId,
    ) {}

    /**
     * Текстовая метка инициатора вызова.
     */
    public function actorLabel(): string
    {
        if ($this->createdByMaxUserId === null) {
            return 'max_user_id=неизвестно';
        }

        return 'max_user_id='.$this->createdByMaxUserId;
    }
}
