<?php

namespace Shared\MaxMessenger\DTO;

readonly class MaxInlineKeyboardButtonDto
{
    public function __construct(
        public string $text,
        public string $payload = '',
        public string $type = 'callback',
        public ?string $webApp = null,
        public ?string $url = null,
        public ?int $contactId = null,
    ) {}
}
