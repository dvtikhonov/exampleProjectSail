<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Отправка приветственного сообщения стенда MAX с inline-клавиатурой.
 */
interface MaxUiStandGreetingSenderInterface
{
    /**
     * Отправляет приветствие всем получателям из конфигурации.
     *
     * @throws \RuntimeException
     */
    public function send(): void;

    /**
     * Отправляет приветствие одному пользователю MAX.
     */
    public function sendToUser(int $userId): void;
}
