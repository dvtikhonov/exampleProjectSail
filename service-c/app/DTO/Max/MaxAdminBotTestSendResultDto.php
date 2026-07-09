<?php

declare(strict_types=1);

namespace App\DTO\Max;

/**
 * Результат отправки тестового сообщения MAX-ботом.
 */
readonly class MaxAdminBotTestSendResultDto
{
    public function __construct(
        public int $sentCount,
    ) {}
}
