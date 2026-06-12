<?php

namespace Shared\MaxMessenger\Exceptions;

class MaxMessengerRateLimitException extends MaxMessengerException
{
    private const MESSAGE = 'Сервис MAX временно ограничил частоту запросов. Повторите отправку позже.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public function userMessage(): string
    {
        return self::MESSAGE;
    }
}
