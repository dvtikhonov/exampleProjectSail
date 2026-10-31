<?php

declare(strict_types=1);

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    /** Временная диагностика connect к api.telegram.org (сессия отладки). */
    'debug_connect' => filter_var(env('TELEGRAM_DEBUG_CONNECT', true), FILTER_VALIDATE_BOOL),

    /** Username бота без @ — для url-кнопок с startapp (Main Mini App). */
    'bot_username' => env('TELEGRAM_BOT_USERNAME', 'obedov_bot'),

    'mini_app_url' => env('TELEGRAM_MINI_APP_URL', 'https://94-228-117-27.sslip.io/tg-app'),

    /**
     * TELEGRAM_REPORT_*: уведомление о доступности меню (паритет MAX_REPORT_*).
     * В Bot API оба списка уходят как chat_id (для DM chat_id = telegram user id).
     */
    'report' => [
        'chat_ids' => array_values(array_filter(array_map(
            static fn (string $id): int => (int) $id,
            array_filter(array_map(
                trim(...),
                explode(',', (string) env('TELEGRAM_REPORT_CHAT_IDS', '')),
            )),
        ))),
        'user_ids' => array_values(array_filter(array_map(
            static fn (string $id): int => (int) $id,
            array_filter(array_map(
                trim(...),
                explode(',', (string) env('TELEGRAM_REPORT_USER_IDS', '')),
            )),
        ))),
    ],

    /**
     * Приветствие после /start (аналог max.ui_stand для Telegram).
     */
    'ui_stand' => [
        'mini_app_button_text' => env('TELEGRAM_UI_STAND_MINI_APP_BUTTON_TEXT', 'Предзаказ готовой еды'),
        'greeting_text' => env(
            'TELEGRAM_UI_STAND_GREETING_TEXT',
            'Привет! Нужно нажать на кнопку "Предзаказ еды".',
        ),
    ],

    /**
     * Webhook входящих Update (личка с ботом → Home_chat + Bot DM).
     *
     * secret_token → заголовок X-Telegram-Bot-Api-Secret-Token (1–256 символов [A-Za-z0-9_-]).
     *
     * @see https://core.telegram.org/bots/api#setwebhook
     */
    'webhook' => [
        'url' => env('TELEGRAM_WEBHOOK_URL', ''),
        'secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
    ],

    'miniapp' => [
        'auth_date_max_age_seconds' => (int) env('TELEGRAM_MINIAPP_AUTH_DATE_MAX_AGE_SECONDS', 3_600),
        'token_ttl_seconds' => (int) env('TELEGRAM_MINIAPP_TOKEN_TTL_SECONDS', 3_600),
    ],

    // Подписанная заглушка initData на localhost:8083/tg-app (только APP_ENV=local|testing).
    'local_dev_init_data' => filter_var(env('TELEGRAM_LOCAL_DEV_INIT_DATA', false), FILTER_VALIDATE_BOOL),

    'local_dev_user_id' => (int) env('TELEGRAM_LOCAL_DEV_USER_ID', 777_001),

    /**
     * Профили демо-пользователей Telegram для локальной отладки в браузере.
     *
     * @var array<int, array{first_name: string, last_name: string, username: string, language_code: string}>
     */
    'local_dev_demo_users' => [
        777_001 => [
            'first_name' => 'Demo',
            'last_name' => 'Telegram',
            'username' => 'demo_telegram',
            'language_code' => 'ru',
        ],
    ],
];
