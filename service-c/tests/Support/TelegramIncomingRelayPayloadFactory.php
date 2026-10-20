<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Фикстуры Telegram Update с message в private chat.
 *
 * @see https://core.telegram.org/bots/api#update
 * @see https://core.telegram.org/bots/api#message
 */
final class TelegramIncomingRelayPayloadFactory
{
    public const int DEFAULT_TELEGRAM_USER_ID = 777_001;

    public const int DEFAULT_DATE_SECONDS = 1_790_152_800;

    public const string DEFAULT_TEXT = 'Здравствуйте, хочу заказ';

    public const string DEFAULT_FIRST_NAME = 'Demo';

    public const string DEFAULT_LAST_NAME = 'Telegram';

    /**
     * Эталонный payload: личка пользователя с текстом.
     *
     * @param  array{
     *     user_id?: int,
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     is_bot?: bool,
     *     text?: string|null,
     *     caption?: string|null,
     *     date?: int,
     *     chat_id?: int|null,
     *     chat_type?: string,
     *     omit_from?: bool,
     *     omit_message?: bool,
     *     use_caption?: bool,
     * }  $overrides
     * @return array<string, mixed>
     */
    public static function privateTextMessage(array $overrides = []): array
    {
        if (($overrides['omit_message'] ?? false) === true) {
            return [
                'update_id' => 1001,
            ];
        }

        $userId = $overrides['user_id'] ?? self::DEFAULT_TELEGRAM_USER_ID;
        $date = $overrides['date'] ?? self::DEFAULT_DATE_SECONDS;
        $chatId = array_key_exists('chat_id', $overrides)
            ? $overrides['chat_id']
            : $userId;

        $from = null;
        if (($overrides['omit_from'] ?? false) !== true) {
            $from = [
                'id' => $userId,
                'is_bot' => $overrides['is_bot'] ?? false,
                'first_name' => array_key_exists('first_name', $overrides)
                    ? ($overrides['first_name'] ?? '')
                    : self::DEFAULT_FIRST_NAME,
                'last_name' => array_key_exists('last_name', $overrides)
                    ? ($overrides['last_name'] ?? '')
                    : self::DEFAULT_LAST_NAME,
            ];
        }

        $message = [
            'message_id' => 42,
            'date' => $date,
            'chat' => [
                'id' => $chatId,
                'type' => $overrides['chat_type'] ?? 'private',
            ],
        ];

        if ($from !== null) {
            $message['from'] = $from;
        }

        if (($overrides['use_caption'] ?? false) === true) {
            $message['caption'] = array_key_exists('caption', $overrides)
                ? ($overrides['caption'] ?? '')
                : self::DEFAULT_TEXT;
        } elseif (array_key_exists('text', $overrides)) {
            if ($overrides['text'] !== null) {
                $message['text'] = $overrides['text'];
            }
        } else {
            $message['text'] = self::DEFAULT_TEXT;
        }

        return [
            'update_id' => 1001,
            'message' => $message,
        ];
    }
}
