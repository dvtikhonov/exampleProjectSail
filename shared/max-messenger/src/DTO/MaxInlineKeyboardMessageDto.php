<?php

namespace Shared\MaxMessenger\DTO;

use InvalidArgumentException;

readonly class MaxInlineKeyboardMessageDto
{
    /**
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     */
    public function __construct(
        public string $text,
        public array $buttonRows,
        public ?int $chatId = null,
        public ?int $userId = null,
    ) {
        if (($chatId === null) === ($userId === null)) {
            throw new InvalidArgumentException('Exactly one of chatId or userId must be set.');
        }

        if ($buttonRows === []) {
            throw new InvalidArgumentException('At least one button row must be provided.');
        }
    }
}
