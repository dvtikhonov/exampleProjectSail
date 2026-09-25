<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\DTO;

/**
 * Входящее текстовое сообщение пользователя боту (из webhook message_created).
 *
 * Источник полей — объект Update MAX: message.sender / message.body / message.recipient.
 *
 * @see https://dev.max.ru/docs-api/objects/Update
 * @see https://dev.max.ru/docs-api/objects/Message
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
    ) {}

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
        );
    }

    private static function toString(mixed $value): string
    {
        return is_string($value) ? $value : '';
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
