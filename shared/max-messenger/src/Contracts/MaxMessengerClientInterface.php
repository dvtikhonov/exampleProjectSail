<?php

namespace Shared\MaxMessenger\Contracts;

use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\DTO\MaxMessageDto;

interface MaxMessengerClientInterface
{
    public function uploadFile(string $contents, string $fileName): string;

    public function sendMessage(MaxMessageDto $message): void;

    public function sendInlineKeyboardMessage(MaxInlineKeyboardMessageDto $message): void;

    public function answerCallback(
        string $callbackId,
        ?string $notification = null,
        ?string $messageText = null,
    ): void;
}
