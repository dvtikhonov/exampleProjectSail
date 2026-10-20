<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Handlers;

use App\Modules\MaxIncomingRelay\Contracts\IncomingMessageRelayServiceInterface;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;

/**
 * Обработка Telegram Update с message: пересылка текста в Home_chat + persist Bot DM.
 */
final class TelegramMessageUpdateHandler
{
    public function __construct(
        private readonly IncomingMessageRelayServiceInterface $relayService,
    ) {}

    /**
     * Разбирает payload Telegram Update и при релевантном message ретранслирует его.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $dto = IncomingBotMessageDto::tryFromTelegram($payload);
        if ($dto === null) {
            return;
        }

        $this->relayService->relay($dto);
    }
}
