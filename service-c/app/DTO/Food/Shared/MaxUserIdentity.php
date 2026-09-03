<?php

declare(strict_types=1);

namespace App\DTO\Food\Shared;

use App\Enums\Food\Review\FoodOrderAdminRole;

/**
 * Идентичность пользователя MAX для доменного слоя Food без Eloquent.
 */
readonly class MaxUserIdentity
{
    /**
     * @param  list<FoodOrderAdminRole>  $adminRoles
     */
    public function __construct(
        public int $maxUserId,
        public array $adminRoles,
    ) {}

    /**
     * Проверяет наличие активной роли администратора заказов.
     */
    public function hasAdminRole(FoodOrderAdminRole $role): bool
    {
        return in_array($role, $this->adminRoles, true);
    }
}
