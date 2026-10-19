<?php

declare(strict_types=1);

namespace App\Exceptions\Telegram;

/**
 * Ошибка ответа Telegram Bot API (HTTP или ok=false).
 */
class TelegramMessengerRequestException extends TelegramMessengerException {}
