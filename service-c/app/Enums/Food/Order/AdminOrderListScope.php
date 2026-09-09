<?php

declare(strict_types=1);

namespace App\Enums\Food\Order;

use App\Enums\Food\Review\FoodOrderAdminRole;

/**
 * Область списка/карточки заказа в админ-API проверки (не путать с OrderRejectionScope).
 */
enum AdminOrderListScope: string
{
    case Address = 'address';
    case Composition = 'composition';

    /**
     * Роль администратора, необходимая для доступа к scope.
     */
    public function requiredRole(): FoodOrderAdminRole
    {
        return match ($this) {
            self::Address => FoodOrderAdminRole::AddressReviewer,
            self::Composition => FoodOrderAdminRole::CompositionReviewer,
        };
    }
}
