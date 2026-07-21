<?php

declare(strict_types=1);

namespace App\Enums\Food;

/**
 * Тип позиции ежедневного меню в уведомлении max_manager.
 */
enum DailyMenuLineType: string
{
    case Single = 'single';
    case Combo = 'combo';
}
