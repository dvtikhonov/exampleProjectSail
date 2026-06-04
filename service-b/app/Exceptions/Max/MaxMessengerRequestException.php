<?php

namespace App\Exceptions\Max;

class MaxMessengerRequestException extends MaxMessengerException
{
    public function __construct(
        private readonly string $safeUserMessage,
    ) {
        parent::__construct($safeUserMessage);
    }

    public function userMessage(): string
    {
        return $this->safeUserMessage;
    }
}
