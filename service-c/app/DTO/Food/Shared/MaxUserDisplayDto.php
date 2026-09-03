<?php

declare(strict_types=1);

namespace App\DTO\Food\Shared;

/**
 * Данные профиля MAX-пользователя для текста уведомлений без Eloquent.
 */
readonly class MaxUserDisplayDto
{
    public function __construct(
        public int $maxUserId,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $username = null,
    ) {}
}
