<?php

namespace App\DTO\Max;

readonly class MaxReportConfig
{
    /**
     * @param  array<int, int>  $chatIds
     * @param  array<int, int>  $userIds
     */
    public function __construct(
        public array $chatIds,
        public array $userIds,
        public string $intro,
        public int $maxTextLength,
        public int $rateLimitRetryMax,
        public int $rateLimitRetryDelayMs,
        public int $interRecipientDelayMs,
    ) {}
}
