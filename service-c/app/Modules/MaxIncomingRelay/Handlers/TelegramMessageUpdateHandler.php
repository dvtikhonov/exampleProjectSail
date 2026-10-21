<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Handlers;

use App\Contracts\Telegram\TelegramBotStartServiceInterface;
use App\Modules\MaxIncomingRelay\Contracts\IncomingMessageRelayServiceInterface;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;

/**
 * Обработка Telegram Update с message: /start → регистрация + экран; иначе релей в Home_chat.
 */
final class TelegramMessageUpdateHandler
{
    public function __construct(
        private readonly IncomingMessageRelayServiceInterface $relayService,
        private readonly TelegramBotStartServiceInterface $botStartService,
    ) {}

    /**
     * Разбирает payload Telegram Update и обрабатывает /start либо ретранслирует текст.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $dto = IncomingBotMessageDto::tryFromTelegram($payload);
        if ($dto === null) {
            return;
        }

        if ($dto->isStartCommand()) {
            $this->botStartService->handleStart(
                telegramUserId: $dto->externalUserId(),
                chatId: $dto->chatId ?? $dto->externalUserId(),
                firstName: $dto->firstName,
                lastName: $dto->lastName !== '' ? $dto->lastName : null,
                username: $dto->username,
                languageCode: $dto->languageCode,
            );

            return;
        }

        $this->relayService->relay($dto);
    }
}
