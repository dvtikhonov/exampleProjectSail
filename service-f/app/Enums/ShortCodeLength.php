<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Допустимая длина короткого кода (согласована с маршрутом redirect и колонкой code).
 */
enum ShortCodeLength: int
{
    /** Минимальная длина кода (совпадает с regex маршрута redirect). */
    case Min = 4;

    /** Длина по умолчанию при генерации. */
    case Default = 8;

    /** Максимальная длина кода (ограничение колонки `code`). */
    case Max = 12;
}
