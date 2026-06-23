<?php

declare(strict_types=1);

namespace App\Enums\Food;

/**
 * Статусы корзины пользователя в mini-app.
 */
enum CartStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
