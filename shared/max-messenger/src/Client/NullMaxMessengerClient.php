<?php

namespace Shared\MaxMessenger\Client;

use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;

/**
 * No-op клиент MAX Bot API: без HTTP-запросов к platform-api.max.ru.
 * Используется при MAX_MESSENGER_DRIVER=null (нагрузочные тесты, локальная изоляция).
 */
class NullMaxMessengerClient implements MaxMessengerClientInterface
{
    public function uploadFile(string $contents, string $fileName): string
    {
        return 'null-file-token';
    }

    public function sendMessage(MaxMessageDto $message): void
    {
    }

    public function sendInlineKeyboardMessage(MaxInlineKeyboardMessageDto $message): void
    {
    }

    public function answerCallback(
        string $callbackId,
        ?string $notification = null,
        ?string $messageText = null,
    ): void {
    }
}
