<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Domain\Enum;

/**
 * Код проблемы матчинга строки графика к блюду/комбо (ответ PhotoText schedule).
 */
enum SchedulePhotoMatchIssueCode: string
{
    case DishNotFound = 'dish_not_found';
    case DishAmbiguous = 'dish_ambiguous';
    case ComboUnresolved = 'combo_unresolved';
}
