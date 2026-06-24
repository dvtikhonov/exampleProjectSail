<?php

declare(strict_types=1);

namespace App\Repositories\Food;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Models\FoodOrderAdmin;

/**
 * Eloquent-реализация репозитория ролей администратора заказов еды.
 */
class EloquentFoodOrderAdminRepository implements FoodOrderAdminRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function hasActiveRole(int $maxUserId, FoodOrderAdminRole $role): bool
    {
        return FoodOrderAdmin::query()
            ->where('max_user_id', $maxUserId)
            ->where('role', $role)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveRoles(int $maxUserId): array
    {
        return FoodOrderAdmin::query()
            ->where('max_user_id', $maxUserId)
            ->where('is_active', true)
            ->pluck('role')
            ->map(static fn (FoodOrderAdminRole|string $role): FoodOrderAdminRole => $role instanceof FoodOrderAdminRole
                ? $role
                : FoodOrderAdminRole::from($role))
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function assignActiveRole(int $maxUserId, FoodOrderAdminRole $role): FoodOrderAdmin
    {
        return FoodOrderAdmin::query()->updateOrCreate(
            [
                'max_user_id' => $maxUserId,
                'role' => $role,
            ],
            [
                'is_active' => true,
            ],
        );
    }
}
