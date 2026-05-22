<?php

namespace App\Enums;

enum SalesOutletStatus: string
{
    case Approved = 'approved';
    case Review = 'review';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Одобрено',
            self::Review => 'На проверке',
            self::Blocked => 'Есть изменения',
        };
    }
}
