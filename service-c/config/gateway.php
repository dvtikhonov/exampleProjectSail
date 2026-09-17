<?php

declare(strict_types=1);

/**
 * Настройки доверенной аутентификации через nginx-gateway (local/testing).
 */
return [
    /**
     * Опциональный секрет заголовка X-Gateway-Secret (сравнение через hash_equals).
     * Пустой — заголовок не требуется (удобно для PHPUnit в local/testing).
     */
    'auth_secret' => (string) env('GATEWAY_AUTH_SECRET', ''),
];
