<?php

declare(strict_types=1);

namespace App\Enums\Food\Menu;

/**
 * День недели по ISO-8601: 1 = понедельник … 7 = воскресенье.
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    /**
     * Список числовых значений enum (1–7).
     *
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
