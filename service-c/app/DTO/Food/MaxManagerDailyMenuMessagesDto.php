<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Два текста уведомления о меню для роли max_manager.
 */
readonly class MaxManagerDailyMenuMessagesDto
{
    public function __construct(
        public string $withoutDelivery,
        public string $withDelivery,
    ) {}
}
