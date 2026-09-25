<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Фикстуры webhook Update message_created в форме реального payload MAX.
 *
 * @see https://dev.max.ru/docs-api/objects/Update
 * @see https://dev.max.ru/docs-api/objects/Message
 */
final class MaxIncomingRelayPayloadFactory
{
    public const int DEFAULT_USER_ID = 54321;

    public const int DEFAULT_TIMESTAMP_MS = 1739184000000;

    public const int DEFAULT_CHAT_ID = -100000000;

    public const int DEFAULT_BOT_USER_ID = 421816864057;

    public const string DEFAULT_TEXT = 'Здравствуйте, хочу заказ';

    public const string DEFAULT_FIRST_NAME = 'Иван';

    public const string DEFAULT_LAST_NAME = 'Петров';

    /**
     * Эталонный payload: диалог пользователя с текстом.
     *
     * @param  array{
     *     user_id?: int,
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     is_bot?: bool,
     *     text?: string|null,
     *     timestamp_ms?: int,
     *     chat_id?: int|null,
     *     omit_sender?: bool,
     *     omit_message?: bool,
     *     omit_body?: bool,
     * }  $overrides
     * @return array<string, mixed>
     */
    public static function textDialog(array $overrides = []): array
    {
        if (($overrides['omit_message'] ?? false) === true) {
            return [
                'update_type' => 'message_created',
                'timestamp' => $overrides['timestamp_ms'] ?? self::DEFAULT_TIMESTAMP_MS,
                'user_locale' => 'ru',
            ];
        }

        $timestampMs = $overrides['timestamp_ms'] ?? self::DEFAULT_TIMESTAMP_MS;

        $sender = null;
        if (($overrides['omit_sender'] ?? false) !== true) {
            $sender = [
                'user_id' => $overrides['user_id'] ?? self::DEFAULT_USER_ID,
                'first_name' => array_key_exists('first_name', $overrides)
                    ? $overrides['first_name']
                    : self::DEFAULT_FIRST_NAME,
                'last_name' => array_key_exists('last_name', $overrides)
                    ? $overrides['last_name']
                    : self::DEFAULT_LAST_NAME,
                'is_bot' => $overrides['is_bot'] ?? false,
                'last_activity_time' => $timestampMs,
                'name' => 'Иван',
            ];
        }

        $body = null;
        if (($overrides['omit_body'] ?? false) !== true) {
            $body = [
                'mid' => 'mid.test-abc',
                'seq' => 0,
            ];
            if (array_key_exists('text', $overrides)) {
                if ($overrides['text'] !== null) {
                    $body['text'] = $overrides['text'];
                }
            } else {
                $body['text'] = self::DEFAULT_TEXT;
            }
        }

        $message = [
            'recipient' => [
                'chat_id' => array_key_exists('chat_id', $overrides)
                    ? $overrides['chat_id']
                    : self::DEFAULT_CHAT_ID,
                'chat_type' => 'dialog',
                'user_id' => self::DEFAULT_BOT_USER_ID,
            ],
            'timestamp' => $timestampMs,
        ];

        if ($sender !== null) {
            $message['sender'] = $sender;
        }

        if ($body !== null) {
            $message['body'] = $body;
        }

        return [
            'update_type' => 'message_created',
            'timestamp' => $timestampMs,
            'user_locale' => 'ru',
            'message' => $message,
        ];
    }
}
