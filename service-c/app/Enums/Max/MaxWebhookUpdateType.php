<?php

declare(strict_types=1);

namespace App\Enums\Max;

/**
 * Типы webhook-обновлений MAX, на которые подписан и которые маршрутизирует UiStand.
 */
enum MaxWebhookUpdateType: string
{
    case MessageCallback = 'message_callback';
    case BotStarted = 'bot_started';
    case MessageCreated = 'message_created';

    /**
     * Строковые значения всех поддерживаемых типов (для subscribe API).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
