<?php

declare(strict_types=1);

/**
 * Настройки доверенной аутентификации через nginx-gateway (local/testing).
 */
return [
    /**
     * Обязательный секрет заголовка X-Gateway-Secret (сравнение через hash_equals).
     * Пустой — TrustGatewayAuth отвечает 401.
     */
    'auth_secret' => (string) env('GATEWAY_AUTH_SECRET', ''),
];
