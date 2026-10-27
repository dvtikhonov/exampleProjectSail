<?php

declare(strict_types=1);

/**
 * Внутренний лог визита страницы выбора Telegram и MAX.
 * Токен общий с service-j (корневой .env, заголовок X-Messenger-Choice-Token).
 */
return [
    'log_token' => env('MESSENGER_CHOICE_LOG_TOKEN', ''),
];
