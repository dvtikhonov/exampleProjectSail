<?php

declare(strict_types=1);

namespace App\DTO\Max;

/**
 * Настройки уведомлений о заказах: получатели и лимит длины текста.
 */
readonly class MaxOrderNotificationConfig
{
    /**
     * @param  list<int>  $chatIds
     * @param  list<int>  $userIds
     */
    public function __construct(
        public array $chatIds,
        public array $userIds,
        public int $maxTextLength,
    ) {}
}
