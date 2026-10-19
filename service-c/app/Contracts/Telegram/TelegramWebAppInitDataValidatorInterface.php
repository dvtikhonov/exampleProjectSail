<?php

declare(strict_types=1);

namespace App\Contracts\Telegram;

use App\DTO\Telegram\TelegramWebAppInitDataDto;
use App\Exceptions\Telegram\TelegramWebAppInitDataException;

/**
 * Валидация и разбор initData Telegram Mini App.
 */
interface TelegramWebAppInitDataValidatorInterface
{
    /**
     * Проверяет подпись и срок действия initData.
     *
     * @throws TelegramWebAppInitDataException
     */
    public function validate(string $initData): TelegramWebAppInitDataDto;
}
