<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Выбор на странице мессенджера.
 * Значение пишется в max_log. Адрес бота сюда не входит.
 */
enum MessengerChoice: string
{
    case Telegram = 'telegram';
    case Max = 'max';
}
