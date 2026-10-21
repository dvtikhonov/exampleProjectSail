<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\DTO;

use App\Enums\MessengerChannel;

/**
 * Входящее текстовое сообщение пользователя боту (MAX message_created или Telegram message).
 *
 * `userId` — внутренний `max_user_id` (для Telegram: OFFSET + telegram_user_id).
 *
 * @see https://dev.max.ru/docs-api/objects/Update
 * @see https://dev.max.ru/docs-api/objects/Message
 * @see https://core.telegram.org/bots/api#update
 */
readonly class IncomingBotMessageDto
{
    public function __construct(
        public int $userId,
        public string $firstName,
        public string $lastName,
        public string $text,
        public int $timestampMs,
        public ?int $chatId,
        public MessengerChannel $messenger = MessengerChannel::Max,
        public ?string $username = null,
        public ?string $languageCode = null,
    ) {}

    /**
     * Внешний id канала (MAX user_id / Telegram user id) для отображения в Home_chat.
     */
    public function externalUserId(): int
    {
        return $this->messenger->externalUserIdFromInternal($this->userId);
    }

    /**
     * Команда /start (с опциональным @botname и deep-link payload).
     */
    public function isStartCommand(): bool
    {
        return (bool) preg_match('/^\/start(?:@[A-Za-z0-9_]+)?(?:\s|$)/u', ltrim($this->text));
    }

    /**
     * Собирает DTO из payload webhook Update MAX либо null, если событие нерелевантно.
     *
     * Требует message.sender.user_id > 0 и is_bot !== true.
     * Без message / sender / body — null (канал без sender, битый payload).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function tryFrom(array $payload): ?self
    {
        $message = $payload['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $sender = $message['sender'] ?? null;
        if (! is_array($sender)) {
            return null;
        }

        if (($sender['is_bot'] ?? false) === true) {
            return null;
        }

        $userId = self::toPositiveInt($sender['user_id'] ?? null);
        if ($userId === null) {
            return null;
        }

        $body = $message['body'] ?? null;
        if (! is_array($body)) {
            return null;
        }

        $text = '';
        if (isset($body['text']) && is_string($body['text'])) {
            $text = $body['text'];
        }

        $firstName = self::toString($sender['first_name'] ?? null);
        $lastName = self::toString($sender['last_name'] ?? null);
        $timestampMs = self::toNonNegativeInt($message['timestamp'] ?? null) ?? 0;

        $chatId = null;
        $recipient = $message['recipient'] ?? null;
        if (is_array($recipient)) {
            $chatId = self::toIntOrNull($recipient['chat_id'] ?? null);
        }

        return new self(
            userId: $userId,
            firstName: $firstName,
            lastName: $lastName,
            text: $text,
            timestampMs: $timestampMs,
            chatId: $chatId,
            messenger: MessengerChannel::Max,
        );
    }

    /**
     * Собирает DTO из Telegram Update (message в private chat) либо null.
     *
     * Пропускает ботов, не-private чаты, апдейты без message / from / положительного id.
     * Текст — message.text или message.caption (пусто допустимо для релея).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function tryFromTelegram(array $payload): ?self
    {
        $message = $payload['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $chat = $message['chat'] ?? null;
        if (! is_array($chat) || ($chat['type'] ?? null) !== 'private') {
            return null;
        }

        $from = $message['from'] ?? null;
        if (! is_array($from)) {
            return null;
        }

        if (($from['is_bot'] ?? false) === true) {
            return null;
        }

        $externalUserId = self::toPositiveInt($from['id'] ?? null);
        if ($externalUserId === null) {
            return null;
        }

        $text = '';
        if (isset($message['text']) && is_string($message['text'])) {
            $text = $message['text'];
        } elseif (isset($message['caption']) && is_string($message['caption'])) {
            $text = $message['caption'];
        }

        $dateSeconds = self::toNonNegativeInt($message['date'] ?? null) ?? 0;
        $chatId = self::toIntOrNull($chat['id'] ?? null);

        $username = self::toOptionalNonEmptyString($from['username'] ?? null);
        $languageCode = self::toOptionalNonEmptyString($from['language_code'] ?? null);

        return new self(
            userId: MessengerChannel::Telegram->toInternalMaxUserId($externalUserId),
            firstName: self::toString($from['first_name'] ?? null),
            lastName: self::toString($from['last_name'] ?? null),
            text: $text,
            timestampMs: $dateSeconds > 0 ? $dateSeconds * 1000 : 0,
            chatId: $chatId,
            messenger: MessengerChannel::Telegram,
            username: $username,
            languageCode: $languageCode,
        );
    }

    private static function toString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function toOptionalNonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function toPositiveInt(mixed $value): ?int
    {
        $int = self::toIntOrNull($value);
        if ($int === null || $int <= 0) {
            return null;
        }

        return $int;
    }

    private static function toNonNegativeInt(mixed $value): ?int
    {
        $int = self::toIntOrNull($value);
        if ($int === null || $int < 0) {
            return null;
        }

        return $int;
    }

    private static function toIntOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return null;
    }
}
