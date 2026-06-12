<?php

namespace Shared\MaxMessenger\Config;

readonly class MaxMessengerRetryConfig
{
    public function __construct(
        public int $rateLimitRetryMax = 2,
        public int $rateLimitRetryDelayMs = 500,
        public int $attachmentNotReadyRetryMax = 3,
        public int $attachmentNotReadyRetryDelayMs = 200,
    ) {}
}
