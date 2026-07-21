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

    /**
     * {@inheritDoc}
     */
    public function listActiveAdminMaxUserIds(): array
    {
        return FoodOrderAdmin::query()
            ->where('is_active', true)
            ->distinct()
            ->orderBy('max_user_id')
            ->pluck('max_user_id')
            ->map(static fn (mixed $maxUserId): int => (int) $maxUserId)
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function listActiveMaxUserIdsByRole(FoodOrderAdminRole $role): array
    {
        return FoodOrderAdmin::query()
            ->where('is_active', true)
            ->where('role', $role)
            ->distinct()
            ->orderBy('max_user_id')
            ->pluck('max_user_id')
            ->map(static fn (mixed $maxUserId): int => (int) $maxUserId)
            ->values()
            ->all();
    }
}
