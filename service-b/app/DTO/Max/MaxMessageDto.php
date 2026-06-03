<?php

namespace App\DTO\Max;

use InvalidArgumentException;

readonly class MaxMessageDto
{
    public function __construct(
        public string $text,
        public ?int $chatId = null,
        public ?int $userId = null,
        public ?string $fileAttachmentToken = null,
    ) {
        if (($chatId === null) === ($userId === null)) {
            throw new InvalidArgumentException('Exactly one of chatId or userId must be set.');
        }
    }
}
