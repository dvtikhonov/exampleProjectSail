<?php

declare(strict_types=1);

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    /** Username бота без @ — для url-кнопок с startapp (Main Mini App). */
    'bot_username' => env('TELEGRAM_BOT_USERNAME', 'obedov_bot'),

    'mini_app_url' => env('TELEGRAM_MINI_APP_URL', 'https://94-228-117-27.sslip.io/tg-app'),

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
