<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Канал мессенджера для пользователя в таблице max_users.
 *
 * Внутренний PK `max_user_id` для Telegram смещён на {@see self::TELEGRAM_MAX_USER_ID_OFFSET},
 * чтобы не пересекаться с id MAX в том же числовом диапазоне.
 */
enum MessengerChannel: string
{
    case Max = 'max';
    case Telegram = 'telegram';

    /**
     * Детерминированный offset для внутреннего `max_user_id` Telegram-пользователей.
     * Константа в коде (не в .env).
     */
    public const int TELEGRAM_MAX_USER_ID_OFFSET = 1_000_000_000_000_000;

    /**
     * Строит внутренний `max_user_id` из внешнего id канала.
     *
     * Для MAX: `max_user_id = external_user_id`.
     * Для Telegram: `max_user_id = OFFSET + telegram_user_id`.
     */
    public function toInternalMaxUserId(int $externalUserId): int
    {
        return match ($this) {
            self::Max => $externalUserId,
            self::Telegram => self::TELEGRAM_MAX_USER_ID_OFFSET + $externalUserId,
        };
    }

    /**
     * Восстанавливает внешний id из внутреннего `max_user_id` при известном канале.
     *
     * Предпочтительно читать колонку `external_user_id`; метод — fallback по формуле offset.
     */
    public function externalUserIdFromInternal(int $maxUserId): int
    {
        return match ($this) {
            self::Max => $maxUserId,
            self::Telegram => $maxUserId - self::TELEGRAM_MAX_USER_ID_OFFSET,
        };
    }
}
