<?php

namespace Shared\MaxMessenger\Exceptions;

class MaxMessengerAuthException extends MaxMessengerException
{
    private const MESSAGE = 'Токен MAX недействителен или отозван. Обновите MAX_BOT_ACCESS_TOKEN в настройках сервиса.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public function userMessage(): string
    {
        return self::MESSAGE;
    }
}
