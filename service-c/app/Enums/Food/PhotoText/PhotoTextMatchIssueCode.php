<?php

declare(strict_types=1);

namespace App\Enums\Food\PhotoText;

/**
 * Код проблемы матчинга строки PhotoText к блюду/комбо.
 */
enum PhotoTextMatchIssueCode: string
{
    case DishNotFound = 'dish_not_found';
    case DishAmbiguous = 'dish_ambiguous';
    case ComboUnresolved = 'combo_unresolved';
}
