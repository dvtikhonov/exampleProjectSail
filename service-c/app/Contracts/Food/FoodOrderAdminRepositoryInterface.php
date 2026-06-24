<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Enums\Food\FoodOrderAdminRole;
use App\Models\FoodOrderAdmin;

/**
 * Репозиторий ролей администратора проверки заказов еды.
 */
interface FoodOrderAdminRepositoryInterface
{
    public function hasActiveRole(int $maxUserId, FoodOrderAdminRole $role): bool;

    /**
     * @return list<FoodOrderAdminRole>
     */
    public function getActiveRoles(int $maxUserId): array;

    /**
     * Назначает или реактивирует роль администратора для пользователя MAX.
     */
    public function assignActiveRole(int $maxUserId, FoodOrderAdminRole $role): FoodOrderAdmin;
}
