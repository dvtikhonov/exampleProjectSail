<?php

declare(strict_types=1);

namespace App\DTO\Max;

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
