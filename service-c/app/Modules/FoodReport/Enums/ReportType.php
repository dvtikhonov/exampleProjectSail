<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Enums;

/**
 * Тип отчёта Food (выгрузка / выбор в UI).
 */
enum ReportType: string
{
    case Revenue = 'revenue';
    case TopDishes = 'top_dishes';

    /**
     * Человекочитаемое название для UI / подписи в MAX.
     */
    public function label(): string
    {
        return match ($this) {
            self::Revenue => 'Выручка за период',
            self::TopDishes => 'Топ позиций',
        };
    }
}
