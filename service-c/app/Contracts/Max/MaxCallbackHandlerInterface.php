<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxCallbackUpdateDto;

/**
 * Обработка нажатий inline-кнопок стенда MAX.
 */
interface MaxCallbackHandlerInterface
{
    /**
     * Отвечает на callback кнопки сообщением или уведомлением.
     */
    public function handle(MaxCallbackUpdateDto $update): void;
}
