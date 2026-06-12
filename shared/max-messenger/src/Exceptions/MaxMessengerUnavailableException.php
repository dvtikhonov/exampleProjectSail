<?php

namespace Shared\MaxMessenger\Exceptions;

class MaxMessengerUnavailableException extends MaxMessengerException
{
    private const MESSAGE = 'Сервис MAX временно недоступен. Повторите отправку позже.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public function userMessage(): string
    {
        return self::MESSAGE;
    }
}
