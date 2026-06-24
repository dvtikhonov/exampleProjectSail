<?php

declare(strict_types=1);

namespace App\Enums\Food;

/**
 * Тип автора сообщения в чате заказа (вычисляется по роли, в БД не хранится).
 */
enum OrderMessageAuthorType: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
