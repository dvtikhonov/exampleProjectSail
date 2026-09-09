<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxMiniAppAccessContextDto;

/**
 * Логирование обращений к MAX mini-app (страница и auth) без секретов.
 */
interface MaxMiniAppAccessLoggerInterface
{
    /**
     * Логирует обращение к странице mini-app.
     */
    public function logPageRequest(MaxMiniAppAccessContextDto $context): void;

    /**
     * Логирует запрос аутентификации mini-app.
     */
    public function logAuthRequest(
        MaxMiniAppAccessContextDto $context,
        int $statusCode,
        ?int $maxUserId = null,
    ): void;
}
