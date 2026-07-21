<?php

declare(strict_types=1);

namespace App\Enums\Food;

/**
 * Роль администратора проверки заказов и меню еды в MAX mini-app.
 */
enum FoodOrderAdminRole: string
{
    case AddressReviewer = 'address_reviewer';
    case CompositionReviewer = 'composition_reviewer';
    case MenuManager = 'menu_manager';
    case MaxManager = 'max_manager';
}
