<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxMiniAppAccessContextDto;
use App\Enums\MessengerChoice;

/**
 * Логирование обращений к MAX mini-app и странице выбора мессенджера без секретов.
 */
interface MaxMiniAppAccessLoggerInterface
{
    /**
     * Логирует обращение к странице mini-app.
     */
    public function logPageRequest(MaxMiniAppAccessContextDto $context): void;

    /**
     * Логирует заход на страницу выбора Telegram и MAX.
     */
    public function logMessengerChoicePageRequest(MaxMiniAppAccessContextDto $context): void;

    /**
     * Логирует клик по мессенджеру. В контекст попадает код выбора, не адрес бота.
     */
    public function logMessengerChoiceClick(MaxMiniAppAccessContextDto $context, MessengerChoice $choice): void;

    /**
     * Логирует запрос аутентификации mini-app.
     */
    public function logAuthRequest(
        MaxMiniAppAccessContextDto $context,
        int $statusCode,
        ?int $maxUserId = null,
    ): void;
}
