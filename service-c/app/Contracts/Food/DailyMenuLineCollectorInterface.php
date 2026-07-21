<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\DailyMenuLineDto;

/**
 * Сбор позиций ежедневного меню (одиночные блюда и комбо-пары).
 */
interface DailyMenuLineCollectorInterface
{
    /**
     * Собирает позиции меню из доступных блюд.
     *
     * @return list<DailyMenuLineDto>
     */
    public function collect(): array;
}
