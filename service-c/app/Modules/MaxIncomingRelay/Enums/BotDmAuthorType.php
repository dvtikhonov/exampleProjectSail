<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Enums;

/**
 * Тип автора сообщения в личке с ботом (хранится в max_bot_direct_messages.author_type).
 */
enum BotDmAuthorType: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
