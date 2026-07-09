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

    // Подписанная заглушка initData на localhost:8083/max-app (только APP_ENV=local|testing).
    'local_dev_init_data' => filter_var(env('MAX_LOCAL_DEV_INIT_DATA', false), FILTER_VALIDATE_BOOL),

    // Каким демо-пользователем открывать /max-app в браузере (профили — local_dev_demo_users).
    // 'local_dev_user_id' => (int) env('MAX_LOCAL_DEV_USER_ID', 1003),
     'local_dev_user_id' => (int) env('MAX_LOCAL_DEV_USER_ID', 1002),

    /**
     * Профили демо-пользователей MAX (синхронизированы с Database\Seeders).
     *
     * @var array<int, array{first_name: string, last_name: string, username: string, language_code: string}>
     */
    'local_dev_demo_users' => [
        1001 => [
            'first_name' => 'Demo',
            'last_name' => 'Стандарт',
            'username' => 'demo_standard',
            'language_code' => 'ru',
        ],
        1002 => [
            'first_name' => 'Demo',
            'last_name' => 'VIP',
            'username' => 'demo_vip',
            'language_code' => 'ru',
        ],
        1003 => [
            'first_name' => 'Demo',
            'last_name' => 'Админ адреса',
            'username' => 'demo_address_admin',
            'language_code' => 'ru',
        ],
        1004 => [
            'first_name' => 'Demo',
            'last_name' => 'Админ состава',
            'username' => 'demo_composition_admin',
            'language_code' => 'ru',
        ],
    ],

    // Уведомления о заказах еды в MAX-чаты отчётов (те же MAX_REPORT_* env, что в service-b).
    'order_notifications' => [
        'chat_ids' => array_values(array_filter(array_map(
            static fn (string $id): int => (int) $id,
            array_filter(array_map(
                trim(...),
                explode(',', (string) env('MAX_REPORT_CHAT_IDS', '')),
            )),
        ))),
        'user_ids' => array_values(array_filter(array_map(
            static fn (string $id): int => (int) $id,
            array_filter(array_map(
                trim(...),
                explode(',', (string) env('MAX_REPORT_USER_IDS', '')),
            )),
        ))),
        'max_text_length' => 4000,
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
