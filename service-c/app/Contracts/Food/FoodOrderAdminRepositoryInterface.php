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
    /**
     * Проверяет наличие активной роли администратора у пользователя MAX.
     */
    public function hasActiveRole(int $maxUserId, FoodOrderAdminRole $role): bool;

    /**
     * Возвращает активные роли администратора пользователя MAX.
     *
     * @return list<FoodOrderAdminRole>
     */
    public function getActiveRoles(int $maxUserId): array;

    /**
     * Назначает или реактивирует роль администратора для пользователя MAX.
     */
    public function assignActiveRole(int $maxUserId, FoodOrderAdminRole $role): FoodOrderAdmin;

    /**
     * Уникальные max_user_id всех активных администраторов заказов.
     *
     * @return list<int>
     */
    public function listActiveAdminMaxUserIds(): array;
}
