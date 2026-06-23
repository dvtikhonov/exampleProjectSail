<?php

declare(strict_types=1);

namespace App\Enums\Food;

/**
 * Статусы заказа еды.
 */
enum OrderStatus: string
{
    case Submitted = 'submitted';
}
