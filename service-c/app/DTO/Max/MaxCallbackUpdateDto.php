<?php

namespace App\DTO\Max;

use InvalidArgumentException;

readonly class MaxCallbackUpdateDto
{
    public function __construct(
        public string $callbackId,
        public string $payload,
        public ?int $userId = null,
        public ?int $chatId = null,
    ) {
        if (($userId === null) === ($chatId === null)) {
            throw new InvalidArgumentException('Exactly one of userId or chatId must be set.');
        }
    }
}
