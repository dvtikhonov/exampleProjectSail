<?php

return [
    'bot_access_token' => env('MAX_BOT_ACCESS_TOKEN', ''),
    'bot_username' => env('MAX_BOT_USERNAME', ''),
    'bot_user_id' => (int) env('MAX_BOT_USER_ID', 0),

    'rate_limit_retry_max' => (int) env('MAX_RATE_LIMIT_RETRY_MAX', 2),
    'rate_limit_retry_delay_ms' => (int) env('MAX_RATE_LIMIT_RETRY_DELAY_MS', 500),
    'attachment_not_ready_retry_max' => (int) env('MAX_ATTACHMENT_NOT_READY_RETRY_MAX', 3),
    'attachment_not_ready_retry_delay_ms' => (int) env('MAX_ATTACHMENT_NOT_READY_RETRY_DELAY_MS', 200),

    'webhook' => [
        'url' => env('MAX_WEBHOOK_URL', ''),
        'secret' => env('MAX_WEBHOOK_SECRET', ''),
        'clean_removable_host_suffixes' => [
            '.trycloudflare.com',
        ],
    ],

    // Публичный URL для asset/API при запросах через туннель (если APP_URL=localhost).
    // По умолчанию выводится из MAX_WEBHOOK_URL (origin без пути).
    'public_app_url' => env('MAX_PUBLIC_APP_URL', ''),

    'miniapp' => [
        'auth_date_max_age_seconds' => (int) env('MAX_MINIAPP_AUTH_DATE_MAX_AGE_SECONDS', 86_400),
        'token_ttl_seconds' => (int) env('MAX_MINIAPP_TOKEN_TTL_SECONDS', 86_400),
    ],

    'ui_stand' => [
        'mini_app_url' => env('MAX_MINI_APP_URL', ''),
        'mini_app_button_text' => env('MAX_UI_STAND_MINI_APP_BUTTON_TEXT', 'Заказ еды'),
        'greeting_text' => env('MAX_UI_STAND_GREETING', 'Привет! Выберите ответ:'),
        'button_yes_payload' => 'yes',
        'button_no_payload' => 'no',
        'recipient_chat_ids' => array_values(array_filter(array_map(
            static fn (string $id): int => (int) $id,
            array_filter(array_map(
                trim(...),
                explode(',', (string) env('MAX_UI_STAND_CHAT_IDS', '')),
            )),
        ))),
        'recipient_user_ids' => array_values(array_filter(array_map(
            static fn (string $id): int => (int) $id,
            array_filter(array_map(
                trim(...),
                explode(',', (string) env('MAX_UI_STAND_USER_IDS', '')),
            )),
        ))),
    ],
];
